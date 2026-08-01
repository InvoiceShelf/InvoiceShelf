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
        ])->find($invoice->id);
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $invoice = Invoice::find($id);

            if ($invoice->transactions()->exists()) {
                $invoice->transactions()->delete();
            }

            // Deleting a credit note reverses the settlement it applied to its
            // original invoice (mirror of the create-side adjustment; same
            // symmetry PR #536 implemented). The balance is recomputed from
            // recorded payments — integer cents throughout — rather than
            // restored from a snapshot, so it is exact even if the invoice was
            // partially paid before being reversed. Skipped when the original
            // is deleted in the same batch.
            if ($invoice->isCreditNote() && $invoice->related_invoice_id && ! $ids->contains($invoice->related_invoice_id)) {
                $original = $invoice->relatedInvoice;

                if ($original) {
                    $dueAmount = (int) $original->total - (int) $original->payments()->sum('amount');
                    $original->due_amount = $dueAmount;
                    $original->base_due_amount = $dueAmount * $original->exchange_rate;
                    $original->changeInvoiceStatus($dueAmount);

                    // changeInvoiceStatus() only persists for amounts >= 0;
                    // make sure the balance itself is saved in every case.
                    if ($original->isDirty()) {
                        $original->save();
                    }
                }
            }

            $invoice->delete();
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

    /**
     * Create a credit note (Stornorechnung) that reverses the given invoice.
     *
     * The credit note is stored as an invoice row with type = CREDIT_NOTE and a
     * reference back to the original invoice. Every monetary field is negated.
     * All amounts are integer cents; negation is exact integer arithmetic, so no
     * float ever touches a currency value (issue #10 from PR #536).
     */
    public function createCreditNote(Invoice $invoice): Invoice
    {
        $invoice->load(['items.taxes', 'taxes', 'fields']);

        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSettingKey('credit_note_number_format')
            ->setSequenceScope(['type' => Invoice::TYPE_CREDIT_NOTE])
            ->setNextNumbers();

        // exchange_rate is a float multiplier, not a currency amount. base_* fields
        // are derived amounts; we negate the already-integer base_* values directly
        // rather than recomputing through the float rate to avoid rounding drift.
        $creditNote = Invoice::create([
            'creator_id' => auth()->id(),
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'related_invoice_id' => $invoice->id,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            // A reversal is never owed, so it has no due date at all. Leaving it
            // null also keeps the credit note out of every due/aging query.
            'due_date' => null,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->template_name,
            // A credit note gets the ordinary create-review-send lifecycle: born
            // DRAFT so the Send affordances appear, promoted to SENT by send().
            // Nothing is ever owed on it, so paid_status/due_amount below keep
            // it out of the payment flows regardless of status.
            'status' => Invoice::STATUS_DRAFT,
            // The credit note is born settled: it exists to pair with the
            // original invoice, nothing is ever owed on it, so it must never
            // surface as an open (negative) balance in any due/aging view.
            'paid_status' => Invoice::STATUS_PAID,
            'sub_total' => -$invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => -$invoice->discount_val,
            'total' => -$invoice->total,
            'due_amount' => 0,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => -$invoice->tax,
            'tax_included' => $invoice->tax_included,
            'notes' => $invoice->notes,
            'exchange_rate' => $invoice->exchange_rate,
            'base_discount_val' => -$invoice->base_discount_val,
            'base_sub_total' => -$invoice->base_sub_total,
            'base_total' => -$invoice->base_total,
            'base_tax' => -$invoice->base_tax,
            'base_due_amount' => 0,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $creditNote->unique_hash = Hashids::connection(Invoice::class)->encode($creditNote->id);
        $creditNote->save();

        $this->documentItemService->createItems($creditNote, $this->negateItems($invoice->items->toArray()));

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($creditNote, $this->negateTaxes($invoice->taxes->toArray()));
        }

        if ($invoice->fields()->exists()) {
            $customFields = [];

            foreach ($invoice->fields as $field) {
                $customFields[] = [
                    'id' => $field->custom_field_id,
                    'value' => $field->defaultAnswer,
                ];
            }

            $creditNote->addCustomFields($customFields);
        }

        // A full reversal nets the original invoice's balance to exactly zero
        // by construction, so settle it: it must drop out of every "awaiting
        // payment" view. changeInvoiceStatus(0) sets status = COMPLETED and
        // paid_status = PAID and persists; the "cancelled via credit note"
        // distinction (vs. genuinely paid) is carried by the creditNotes
        // relation and surfaced in the UI, so cash-flow reporting stays
        // programmatically simple (same trade-off sevDesk makes; avoids
        // silently breaking existing paid/unpaid aggregates).
        $invoice->due_amount = 0;
        $invoice->base_due_amount = 0;
        $invoice->changeInvoiceStatus(0);

        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
            'relatedInvoice',
        ])->find($creditNote->id);
    }

    /**
     * Negate the monetary columns on copied line items (integer cents in, integer
     * cents out). price/discount_val/tax/total are flipped; quantity is untouched.
     */
    private function negateItems(array $items): array
    {
        return array_map(function (array $item) {
            foreach (['price', 'discount_val', 'tax', 'total'] as $field) {
                if (isset($item[$field])) {
                    $item[$field] = -$item[$field];
                }
            }

            if (! empty($item['taxes'])) {
                $item['taxes'] = $this->negateTaxes($item['taxes']);
            }

            return $item;
        }, $items);
    }

    /**
     * Negate the amount on copied taxes (integer cents).
     */
    private function negateTaxes(array $taxes): array
    {
        return array_map(function (array $tax) {
            if (isset($tax['amount'])) {
                $tax['amount'] = -$tax['amount'];
            }

            return $tax;
        }, $taxes);
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
