<?php

namespace App\Domains\Sales\Application;

use App;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Contracts\EstimateEmailSender;
use App\Domains\Sales\Contracts\EstimatePdfDataProvider;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Facades\Hashids;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfMetadata;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use App\Support\Hashids\HashidConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class EstimateService implements EstimatePdfDataProvider
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly MailConfigurator $mailConfigurator,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly DocumentExchangeRateRecorder $exchangeRateRecorder,
        private readonly EstimateEmailSender $estimateEmailSender,
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
    ): Estimate {
        $estimate = Estimate::create($attributes);
        $estimate->unique_hash = Hashids::connection(HashidConnection::Estimate->value)->encode($estimate->id);
        $serial = (new SerialNumberService)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setModel($estimate)
            ->setNextNumbers();

        // Both sequences fall out of the same resolution pass. The visible
        // number itself is rendered client-side and arrived with the payload.
        $estimate->fill([
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
        ])->save();

        $companyCurrency = CompanySetting::getSetting('currency', $estimate->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($estimate);
        }

        $this->documentItemService->createItems($estimate, $items);

        if ($taxes) {
            $this->documentItemService->createTaxes($estimate, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($estimate, $customFields);
        }

        return $estimate;
    }

    public function update(
        Estimate $estimate,
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): Estimate {
        $serial = (new SerialNumberService)
            ->setCompany($estimate->company_id)
            ->setModel($estimate)
            ->setCustomer($attributes['customer_id'])
            ->setModelObject($estimate->id)
            ->setNextNumbers();

        $attributes['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $estimate->update($attributes);

        $companyCurrency = CompanySetting::getSetting('currency', $estimate->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($estimate);
        }

        // Answers to item-level custom fields have no cascade of their own,
        // so they are cleared row by row before the items are replaced.
        foreach ($estimate->items as $lineItem) {
            foreach ($lineItem->fields()->get() as $answer) {
                $answer->delete();
            }
        }

        $estimate->items()->delete();
        $estimate->taxes()->delete();

        $this->documentItemService->createItems($estimate, $items);

        if ($taxes) {
            $this->documentItemService->createTaxes($estimate, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($estimate, $customFields);
        }

        return Estimate::with(['items.taxes', 'items.fields', 'items.fields.customField', 'customer', 'taxes'])
            ->findOrFail($estimate->id);
    }

    public function sendEstimateData(Estimate $estimate, array $data): array
    {
        $data['estimate'] = $estimate->toArray();
        $data['user'] = $estimate->customer->toArray();
        $data['company'] = $estimate->company->toArray();
        $data['body'] = $estimate->getEmailBody($data['body']);
        $data['attach']['data'] = ($estimate->getEmailAttachmentSetting()) ? $this->getPdfData($estimate) : null;

        return $data;
    }

    public function send(Estimate $estimate, array $data): array
    {
        $data = $this->sendEstimateData($estimate, $data);

        $this->mailConfigurator->applyCompanyConfig($estimate->company_id);

        if ($estimate->status == Estimate::STATUS_DRAFT) {
            $estimate->status = Estimate::STATUS_SENT;
            $estimate->save();
        }

        $this->estimateEmailSender->send($data);

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    public function getPdfData(Estimate $estimate): mixed
    {
        $taxes = collect();

        if ($estimate->tax_per_item === 'YES') {
            foreach ($estimate->items as $item) {
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

        $estimateTemplate = Estimate::find($estimate->id)->template_name;

        $company = Company::find($estimate->company_id);
        $language = CompanySetting::getSetting('language', $company->id);
        // Scoped to this document's company: the definitions become column
        // headers on the rendered page, so an unscoped lookup would print one
        // tenant's field labels on another's documents. Not whereCompany(),
        // which reads the request header and so is wrong for a portal
        // download or a queued mail job.
        $customFields = CustomField::query()
            ->where('company_id', $estimate->company_id)
            ->where('model_type', 'Item')
            ->get();

        App::setLocale($language);

        // Absent for a company that never uploaded one; the templates cope.
        $logo = $company->logo_path;

        View::share([
            'estimate' => $estimate,
            'customFields' => $customFields,
            'logo' => $logo ?? null,
            'company_address' => $estimate->getCompanyAddress(),
            'shipping_address' => $estimate->getCustomerShippingAddress(),
            'billing_address' => $estimate->getCustomerBillingAddress(),
            'notes' => $estimate->getNotes(),
            'taxes' => $taxes,
        ]);

        $templatePath = PdfTemplateUtils::resolveView('estimate', $estimateTemplate, 'estimate1');

        // `?preview` hands back the raw HTML instead of a rendered PDF.
        $wantsHtmlPreview = request()->has('preview');

        if ($wantsHtmlPreview) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath, PdfMetadata::forDocument(
            __('pdf_estimate_label'),
            $estimate->estimate_number,
            $company,
        ));
    }

    public function clone(Estimate $estimate): Estimate
    {
        $date = Carbon::now();

        $serial = (new SerialNumberService)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setModel($estimate)
            ->setNextNumbers();

        $expiryDate = null;
        $expiryEnabled = CompanySetting::getSetting(
            'estimate_set_expiry_date_automatically',
            $estimate->company_id
        );

        if ($expiryEnabled === 'YES') {
            $expiryDays = intval(CompanySetting::getSetting(
                'estimate_expiry_date_days',
                $estimate->company_id
            ));
            $expiryDate = Carbon::now()->addDays($expiryDays)->format('Y-m-d');
        }

        $exchangeRate = $estimate->exchange_rate;

        $newEstimate = Estimate::create([
            'estimate_date' => $date->format('Y-m-d'),
            'expiry_date' => $expiryDate,
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $estimate->reference_number,
            'customer_id' => $estimate->customer_id,
            'company_id' => $estimate->company_id,
            'template_name' => $estimate->template_name,
            'status' => Estimate::STATUS_DRAFT,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'due_amount' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'exchange_rate' => $exchangeRate,
            'base_total' => $estimate->total * $exchangeRate,
            'base_discount_val' => $estimate->discount_val * $exchangeRate,
            'base_sub_total' => $estimate->sub_total * $exchangeRate,
            'base_tax' => $estimate->tax * $exchangeRate,
            'base_due_amount' => $estimate->total * $exchangeRate,
            ...$estimate->only(['currency_id', 'sales_tax_type', 'sales_tax_address_type']),
        ]);

        $newEstimate->unique_hash = Hashids::connection(HashidConnection::Estimate->value)->encode($newEstimate->id);
        $newEstimate->save();

        $estimate->load('items.taxes');
        $this->documentItemService->createItems($newEstimate, $estimate->items->toArray());

        if ($estimate->taxes) {
            $this->documentItemService->createTaxes($newEstimate, $estimate->taxes->toArray());
        }

        if ($estimate->fields()->exists()) {
            $customFields = [];

            foreach ($estimate->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $this->customFieldValueWriter->attach($newEstimate, $customFields);
        }

        return $newEstimate;
    }

    public function convertToInvoice(Estimate $estimate): Invoice
    {
        $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

        $invoiceDate = Carbon::now();
        $dueDate = null;

        $autoDueDate = CompanySetting::getSetting('invoice_set_due_date_automatically', $estimate->company_id);

        if ($autoDueDate === 'YES') {
            $dueDateDays = (int) CompanySetting::getSetting('invoice_due_date_days', $estimate->company_id);
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $serial = (new SerialNumberService)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setModel(new Invoice)
            ->setNextNumbers();

        $invoiceTemplate = $estimate->getInvoiceTemplateName();
        $exchangeRate = $estimate->exchange_rate;

        // Columns the invoice inherits unchanged from the offer it settles.
        $carriedOver = $estimate->only([
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

        $invoice = Invoice::create([
            'creator_id' => Auth::id(),
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            // A second, independent rendering of the same number format rather
            // than a copy of the number above.
            'reference_number' => $serial->getNextNumber(),
            'template_name' => $invoiceTemplate,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'due_amount' => $estimate->total,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => $estimate->discount_val * $exchangeRate,
            'base_sub_total' => $estimate->sub_total * $exchangeRate,
            'base_total' => $estimate->total * $exchangeRate,
            'base_tax' => $estimate->tax * $exchangeRate,
            ...$carriedOver,
        ]);

        $invoice->unique_hash = Hashids::connection(HashidConnection::Invoice->value)->encode($invoice->id);
        $invoice->save();

        $this->documentItemService->createItems($invoice, $estimate->items->toArray());

        if ($estimate->taxes) {
            $this->documentItemService->createTaxes($invoice, $estimate->taxes->toArray());
        }

        if ($estimate->fields()->exists()) {
            $customFields = [];

            foreach ($estimate->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $this->customFieldValueWriter->attach($invoice, $customFields);
        }

        $estimate->checkForEstimateConvertAction();

        return Invoice::find($invoice->id);
    }

    public function changeStatus(Estimate $estimate, string $status): void
    {
        $estimate->update(['status' => $status]);
    }
}
