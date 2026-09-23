<?php

namespace App\Domains\Sales\Application;

use App;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Contracts\InvoicePdfDataProvider;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfMetadata;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\PublicToken;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class InvoiceService implements InvoicePdfDataProvider
{
    /**
     * Relations a single-document payload is always returned with.
     */
    private const DETAIL_RELATIONS = ['items', 'items.fields', 'items.fields.customField', 'customer', 'taxes', 'creditNotes'];

    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly CreditNoteService $creditNoteService,
        private readonly MailConfigurator $mailConfigurator,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly DocumentExchangeRateRecorder $exchangeRateRecorder,
        private readonly InvoiceEmailSender $invoiceEmailSender,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>|null  $taxes
     */
    public function create(
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): Invoice {
        $invoice = Invoice::create($attributes);

        $serial = (new SerialNumberService)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setModel($invoice)
            ->setNextNumbers();

        // Both sequences fall out of the same resolution pass. The visible
        // number itself is rendered client-side and arrived with the payload.
        $invoice->fill([
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
        ]);
        $invoice->unique_hash = PublicToken::make();
        $invoice->save();

        $this->documentItemService->createItems($invoice, $items);

        $companyCurrency = CompanySetting::getSetting('currency', $invoice->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($invoice);
        }

        if ($taxes) {
            $this->documentItemService->createTaxes($invoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($invoice, $customFields);
        }

        return Invoice::with(self::DETAIL_RELATIONS)->findOrFail($invoice->id);
    }

    /**
     * @throws ValidationException
     */
    public function update(
        Invoice $invoice,
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): Invoice {
        $serial = (new SerialNumberService)
            ->setCompany($invoice->company_id)
            ->setModel($invoice)
            ->setCustomer($attributes['customer_id'])
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setModelObject($invoice->id)
            ->setNextNumbers();

        $oldTotal = $invoice->total;

        $totalPaidAmount = $invoice->total - $invoice->due_amount;

        if ($totalPaidAmount > 0 && (int) $invoice->customer_id !== (int) $attributes['customer_id']) {
            throw ValidationException::withMessages([
                'customer_id' => ['customer_cannot_be_changed_after_payment_is_added'],
            ]);
        }

        if ($attributes['total'] >= 0 && $attributes['total'] < $totalPaidAmount) {
            throw ValidationException::withMessages([
                'total' => ['total_invoice_amount_must_be_more_than_paid_amount'],
            ]);
        }

        if ($oldTotal != $attributes['total']) {
            $oldTotal = (int) round($attributes['total']) - (int) $oldTotal;
        } else {
            $oldTotal = 0;
        }

        $attributes['due_amount'] = ($invoice->due_amount + $oldTotal);
        $attributes['base_due_amount'] = $attributes['due_amount'] * $attributes['exchange_rate'];
        $attributes['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $invoice->update($attributes);

        $statusData = $invoice->getInvoiceStatusByAmount($attributes['due_amount']);
        if (! empty($statusData)) {
            $invoice->update($statusData);
        }

        $companyCurrency = CompanySetting::getSetting('currency', $invoice->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($invoice);
        }

        // Answers to item-level custom fields have no cascade of their own,
        // so they are cleared row by row before the items are replaced.
        foreach ($invoice->items as $lineItem) {
            foreach ($lineItem->fields()->get() as $answer) {
                $answer->delete();
            }
        }

        $invoice->items()->delete();
        $invoice->taxes()->delete();

        $this->documentItemService->createItems($invoice, $items);

        if ($taxes) {
            $this->documentItemService->createTaxes($invoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($invoice, $customFields);
        }

        return Invoice::with(self::DETAIL_RELATIONS)->findOrFail($invoice->id);
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

            if ($invoice->allocations()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => ['invoice_has_payment_allocations'],
                ]);
            }

            $transactions = $invoice->transactions();

            if ($transactions->exists()) {
                $transactions->delete();
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

        return ['type' => 'preview', 'view' => new SendInvoiceMail($data)];
    }

    public function send(Invoice $invoice, array $data): array
    {
        $data = $this->sendInvoiceData($invoice, $data);

        $this->mailConfigurator->applyCompanyConfig($invoice->company_id);

        $this->invoiceEmailSender->send($data, $invoice->isCreditNote());

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

    public function getPdfData(Invoice $invoice): mixed
    {
        $taxes = collect();

        if ($invoice->tax_per_item === 'YES') {
            foreach ($invoice->items as $item) {
                foreach ($item->taxes as $appliedTax) {
                    // Rows of one tax type collapse onto the first row seen for
                    // it, which then carries the running total for the document.
                    $running = $taxes->first(fn ($seen) => $seen->tax_type_id == $appliedTax->tax_type_id);

                    if ($running) {
                        $running->amount += $appliedTax->amount;
                    } else {
                        $taxes->push($appliedTax);
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
        $language = CompanySetting::getSetting('language', $company->id);
        // Scoped to this document's company: the definitions become column
        // headers on the rendered page, so an unscoped lookup would print one
        // tenant's field labels on another's documents. Not whereCompany(),
        // which reads the request header and so is wrong for a portal
        // download or a queued mail job.
        $customFields = CustomField::query()
            ->where('company_id', $invoice->company_id)
            ->where('model_type', 'Item')
            ->wherePrinted()
            ->get();

        // Document-level definitions the author asked to have printed. They
        // render in the details block beside the number and the dates, which
        // is where a custom date belongs (#237).
        $documentFields = CustomField::query()
            ->where('company_id', $invoice->company_id)
            ->where('model_type', 'Invoice')
            ->wherePrinted()
            ->orderBy('order')
            ->get();

        App::setLocale($language);

        // Absent for a company that never uploaded one; the templates cope.
        $logo = $company->logo_path;

        View::share([
            'invoice' => $invoice,
            'customFields' => $customFields,
            'documentFields' => $documentFields,
            'company_address' => $invoice->getCompanyAddress(),
            'shipping_address' => $invoice->getCustomerShippingAddress(),
            'billing_address' => $invoice->getCustomerBillingAddress(),
            'notes' => $invoice->getNotes(),
            'logo' => $logo ?? null,
            'taxes' => $taxes,
        ]);

        $templatePath = PdfTemplateUtils::resolveView('invoice', $invoiceTemplate, 'invoice1');

        // `?preview` hands back the raw HTML instead of a rendered PDF.
        $wantsHtmlPreview = request()->has('preview');

        if ($wantsHtmlPreview) {
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
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setModel($invoice)
            ->setNextNumbers();

        $dueDate = null;
        $autoDueDate = CompanySetting::getSetting('invoice_set_due_date_automatically', $invoice->company_id);

        if ($autoDueDate === 'YES') {
            $dueDateDays = (int) CompanySetting::getSetting('invoice_due_date_days', $invoice->company_id);
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $exchangeRate = $invoice->exchange_rate;

        // Columns the copy inherits unchanged. Everything outside this list is
        // either dated today, renumbered, or derived from the exchange rate.
        $carriedOver = $invoice->only([
            'reference_number',
            'customer_id',
            'company_id',
            'template_name',
            'currency_id',
            'sub_total',
            'discount',
            'discount_type',
            'discount_val',
            'tax',
            'total',
            'tax_per_item',
            'discount_per_item',
            'notes',
            'sales_tax_type',
            'sales_tax_address_type',
        ]);

        $newInvoice = Invoice::create([
            'invoice_date' => $date->toDateString(),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'due_amount' => $invoice->total,
            'exchange_rate' => $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            'base_due_amount' => $invoice->total * $exchangeRate,
            ...$carriedOver,
        ]);

        $newInvoice->unique_hash = PublicToken::make();
        $newInvoice->save();

        $invoice->load('items.taxes');
        $this->documentItemService->createItems($newInvoice, $this->documentItemService->itemsForCopy($invoice));

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($newInvoice, $invoice->taxes->toArray());
        }

        if ($invoice->fields()->exists()) {
            $customFields = $invoice->fields->map(fn ($answer) => [
                'id' => $answer->custom_field_id,
                'value' => $answer->defaultAnswer,
            ])->all();

            $this->customFieldValueWriter->attach($newInvoice, $customFields);
        }

        return $newInvoice;
    }

    public function convertToEstimate(Invoice $invoice): Estimate
    {
        $invoice->load(['items', 'items.taxes', 'customer', 'taxes']);

        $serial = (new SerialNumberService)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setModel(new Estimate)
            ->setNextNumbers();

        $exchangeRate = $invoice->exchange_rate;

        // Columns the offer inherits unchanged from the document it replaces.
        $carriedOver = $invoice->only([
            'creator_id',
            'customer_id',
            'company_id',
            'currency_id',
            'sub_total',
            'discount',
            'discount_type',
            'discount_val',
            'tax',
            'total',
            'tax_per_item',
            'discount_per_item',
            'notes',
            'sales_tax_type',
            'sales_tax_address_type',
        ]);

        $estimate = Estimate::create([
            'estimate_date' => Carbon::now()->format('Y-m-d'),
            'expiry_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            // A second, independent rendering of the same number format rather
            // than a copy of the number above.
            'reference_number' => $serial->getNextNumber(),
            'template_name' => $invoice->getEstimateTemplateName(),
            'status' => Estimate::STATUS_DRAFT,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            ...$carriedOver,
        ]);

        $estimate->unique_hash = PublicToken::make();
        $estimate->save();

        $this->documentItemService->createItems($estimate, $this->documentItemService->itemsForCopy($invoice));

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($estimate, $invoice->taxes->toArray());
        }

        if ($invoice->fields()->exists()) {
            $customFields = $invoice->fields->map(fn ($answer) => [
                'id' => $answer->custom_field_id,
                'value' => $answer->defaultAnswer,
            ])->all();

            $this->customFieldValueWriter->attach($estimate, $customFields);
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
            $paid = (int) $invoice->allocations()->sum('amount');
            $credited = $this->creditNoteService->creditedTotal($invoice);
            $outstanding = max(0, (int) $invoice->total - $paid - $credited);

            if (
                $outstanding !== 0
                || (int) $invoice->due_amount !== 0
                || (int) $invoice->base_due_amount !== 0
            ) {
                throw ValidationException::withMessages([
                    'status' => ['invoice_must_be_settled_before_completion'],
                ]);
            }

            $invoice->changeInvoiceStatus((int) $invoice->due_amount);
        }
    }
}
