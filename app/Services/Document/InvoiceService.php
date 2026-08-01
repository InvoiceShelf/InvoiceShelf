<?php

namespace App\Services\Document;

use App;
use App\Facades\Hashids;
use App\Facades\Pdf;
use App\Mail\SendCreditNoteMail;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\Mail\CompanyMailConfigService;
use App\Support\Pdf\PdfMetadata;
use App\Support\Pdf\PdfTemplateUtils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly CreditNoteService $creditNoteService,
    ) {}

    public function create(Request $request): Invoice
    {
        $data = $request->getInvoicePayload();

        if ($request->has('invoiceSend')) {
            $data['status'] = Invoice::STATUS_SENT;
        }

        $invoice = Invoice::create($data);

        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setNextNumbers();

        $invoice->sequence_number = $serial->nextSequenceNumber;
        $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        $this->documentItemService->createItems($invoice, $request->items);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($invoice);
        }

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($invoice, $request->taxes);
        }

        if ($request->customFields) {
            $invoice->addCustomFields($request->customFields);
        }

        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
            'creditNotes',
        ])->find($invoice->id);
    }

    /**
     * @throws ValidationException
     */
    public function update(Invoice $invoice, Request $request): Invoice
    {
        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($request->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setModelObject($invoice->id)
            ->setNextNumbers();

        $data = $request->getInvoicePayload();
        $oldTotal = $invoice->total;

        $totalPaidAmount = $invoice->total - $invoice->due_amount;

        if ($totalPaidAmount > 0 && $invoice->customer_id !== $request->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => ['customer_cannot_be_changed_after_payment_is_added'],
            ]);
        }

        if ($data['total'] >= 0 && $data['total'] < $totalPaidAmount) {
            throw ValidationException::withMessages([
                'total' => ['total_invoice_amount_must_be_more_than_paid_amount'],
            ]);
        }

        if ($oldTotal != $data['total']) {
            $oldTotal = (int) round($data['total']) - (int) $oldTotal;
        } else {
            $oldTotal = 0;
        }

        $data['due_amount'] = ($invoice->due_amount + $oldTotal);
        $data['base_due_amount'] = $data['due_amount'] * $data['exchange_rate'];
        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $invoice->update($data);

        $statusData = $invoice->getInvoiceStatusByAmount($data['due_amount']);
        if (! empty($statusData)) {
            $invoice->update($statusData);
        }

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($invoice);
        }

        $invoice->items->map(function ($item) {
            $fields = $item->fields()->get();

            $fields->map(function ($field) {
                $field->delete();
            });
        });

        $invoice->items()->delete();
        $invoice->taxes()->delete();

        $this->documentItemService->createItems($invoice, $request->items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($invoice, $request->taxes);
        }

        if ($request->customFields) {
            $invoice->updateCustomFields($request->customFields);
        }

        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
            'creditNotes',
        ])->find($invoice->id);
    }

    public function delete(Collection $ids): bool
    {
        // Invoices that lose a credit note in this batch and survive it. Their
        // balances are recomputed once, after every deletion has landed, so a
        // batch deleting several credit notes of the same invoice settles on
        // the right figure instead of one per deleted document.
        $creditedInvoiceIds = [];

        foreach ($ids as $id) {
            $invoice = Invoice::find($id);

            if ($invoice->transactions()->exists()) {
                $invoice->transactions()->delete();
            }

            if ($invoice->isCreditNote() && $invoice->related_invoice_id && ! $ids->contains($invoice->related_invoice_id)) {
                $creditedInvoiceIds[$invoice->related_invoice_id] = $invoice->related_invoice_id;
            }

            $invoice->delete();
        }

        // There is no DB-level foreign key on related_invoice_id by convention,
        // so the cascade lives here: nothing that survives the batch may keep
        // pointing at a row that just went away.
        Invoice::whereIn('related_invoice_id', $ids)->update(['related_invoice_id' => null]);

        // Deleting a credit note gives back the amount it had credited off its
        // original invoice (mirror of the create-side adjustment; same symmetry
        // PR #536 implemented). The balance is recomputed from the payments and
        // the credit notes that remain rather than restored from a snapshot, so
        // it is exact whether the invoice was partly paid, partly credited, or
        // both.
        foreach ($creditedInvoiceIds as $creditedInvoiceId) {
            $original = Invoice::find($creditedInvoiceId);

            if ($original) {
                $this->creditNoteService->recalculateBalance($original);
            }
        }

        return true;
    }

    public function sendInvoiceData(Invoice $invoice, array $data): array
    {
        $data['invoice'] = $invoice->toArray();
        $data['customer'] = $invoice->customer->toArray();
        $data['company'] = Company::find($invoice->company_id);
        $data['subject'] = $invoice->getEmailString($data['subject']);
        $data['body'] = $invoice->getEmailString($data['body']);
        $data['attach']['data'] = ($invoice->getEmailAttachmentSetting()) ? $this->getPdfData($invoice) : null;

        return $data;
    }

    public function preview(Invoice $invoice, array $data): array
    {
        $data = $this->sendInvoiceData($invoice, $data);

        return [
            'type' => 'preview',
            'view' => new SendInvoiceMail($data),
        ];
    }

    public function send(Invoice $invoice, array $data): array
    {
        $data = $this->sendInvoiceData($invoice, $data);

        CompanyMailConfigService::apply($invoice->company_id);

        $mail = \Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }
        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }
        // A credit note travels through the same send channel as the invoice it
        // reverses; only the template (and its EmailLog entry) differs.
        $mail->send($invoice->isCreditNote()
            ? new SendCreditNoteMail($data)
            : new SendInvoiceMail($data));

        if ($invoice->status == Invoice::STATUS_DRAFT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        }

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    public function getPdfData(Invoice $invoice)
    {
        $taxes = collect();

        if ($invoice->tax_per_item === 'YES') {
            foreach ($invoice->items as $item) {
                foreach ($item->taxes as $tax) {
                    $found = $taxes->filter(function ($item) use ($tax) {
                        return $item->tax_type_id == $tax->tax_type_id;
                    })->first();

                    if ($found) {
                        $found->amount += $tax->amount;
                    } else {
                        $taxes->push($tax);
                    }
                }
            }
        }

        $invoiceTemplate = Invoice::find($invoice->id)->template_name;

        // Cheap either way: relatedInvoice is null for regular invoices and
        // creditNotes is empty for credit notes. Eager-loaded here so the
        // invoice templates can reference the paired document.
        $invoice->loadMissing(['relatedInvoice', 'creditNotes']);

        $company = Company::find($invoice->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);
        $customFields = CustomField::where('model_type', 'Item')->get();

        App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'invoice' => $invoice,
            'customFields' => $customFields,
            'company_address' => $invoice->getCompanyAddress(),
            'shipping_address' => $invoice->getCustomerShippingAddress(),
            'billing_address' => $invoice->getCustomerBillingAddress(),
            'notes' => $invoice->getNotes(),
            'logo' => $logo ?? null,
            'taxes' => $taxes,
        ]);

        $templatePath = PdfTemplateUtils::resolveView('invoice', $invoiceTemplate, 'invoice1');

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath, PdfMetadata::forDocument(
            __($invoice->isCreditNote() ? 'pdf_credit_note_label' : 'pdf_invoice_label'),
            $invoice->invoice_number,
            $company,
        ));
    }

    public function clone(Invoice $invoice): Invoice
    {
        $date = Carbon::now();

        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setNextNumbers();

        $dueDate = null;
        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $invoice->company_id
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $invoice->company_id
            ));
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $exchangeRate = $invoice->exchange_rate;

        $newInvoice = Invoice::create([
            'invoice_date' => $date->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $invoice->reference_number,
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->template_name,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => $invoice->discount_val,
            'total' => $invoice->total,
            'due_amount' => $invoice->total,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => $invoice->tax,
            'notes' => $invoice->notes,
            'exchange_rate' => $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            'base_due_amount' => $invoice->total * $exchangeRate,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $newInvoice->unique_hash = Hashids::connection(Invoice::class)->encode($newInvoice->id);
        $newInvoice->save();

        $invoice->load('items.taxes');
        $this->documentItemService->createItems($newInvoice, $invoice->items->toArray());

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($newInvoice, $invoice->taxes->toArray());
        }

        if ($invoice->fields()->exists()) {
            $customFields = [];

            foreach ($invoice->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $newInvoice->addCustomFields($customFields);
        }

        return $newInvoice;
    }

    public function convertToEstimate(Invoice $invoice): Estimate
    {
        $invoice->load(['items', 'items.taxes', 'customer', 'taxes']);

        $serial = (new SerialNumberService)
            ->setModel(new Estimate)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $exchangeRate = $invoice->exchange_rate;

        $estimate = Estimate::create([
            'creator_id' => $invoice->creator_id,
            'estimate_date' => Carbon::now()->format('Y-m-d'),
            'expiry_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $serial->getNextNumber(),
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->getEstimateTemplateName(),
            'status' => Estimate::STATUS_DRAFT,
            'sub_total' => $invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => $invoice->discount_val,
            'total' => $invoice->total,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => $invoice->tax,
            'notes' => $invoice->notes,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $estimate->save();

        $this->documentItemService->createItems($estimate, $invoice->items->toArray());

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($estimate, $invoice->taxes->toArray());
        }

        if ($invoice->fields()->exists()) {
            $customFields = [];

            foreach ($invoice->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $estimate->addCustomFields($customFields);
        }

        return $estimate;
    }

    public function changeStatus(Invoice $invoice, string $status): void
    {
        if ($status == Invoice::STATUS_SENT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        } elseif ($status == Invoice::STATUS_COMPLETED) {
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->due_amount = 0;
            $invoice->save();
        }
    }
}
