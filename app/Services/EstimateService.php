<?php

namespace App\Services;

use App;
use App\Facades\Hashids;
use App\Facades\Pdf;
use App\Mail\SendEstimateMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\Pdf\PdfTemplateUtils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstimateService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
    ) {}

    public function create(Request $request): Estimate
    {
        $data = $request->getEstimatePayload();

        if ($request->has('estimateSend')) {
            $data['status'] = Estimate::STATUS_SENT;
        }

        $estimate = Estimate::create($data);
        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $estimate->sequence_number = $serial->nextSequenceNumber;
        $estimate->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $estimate->save();

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($estimate);
        }

        $this->documentItemService->createItems($estimate, $request->items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($estimate, $request->taxes);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $estimate->addCustomFields($customFields);
        }

        return $estimate;
    }

    public function update(Estimate $estimate, Request $request): Estimate
    {
        $data = $request->getEstimatePayload();

        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($estimate->id)
            ->setNextNumbers();

        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $estimate->update($data);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($estimate);
        }

        $estimate->items->map(function ($item) {
            $fields = $item->fields()->get();

            $fields->map(function ($field) {
                $field->delete();
            });
        });

        $estimate->items()->delete();
        $estimate->taxes()->delete();

        $this->documentItemService->createItems($estimate, $request->items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($estimate, $request->taxes);
        }

        if ($request->customFields) {
            $estimate->updateCustomFields($request->customFields);
        }

        return Estimate::with([
            'items.taxes',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ])->find($estimate->id);
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

        CompanyMailConfigService::apply($estimate->company_id);

        if ($estimate->status == Estimate::STATUS_DRAFT) {
            $estimate->status = Estimate::STATUS_SENT;
            $estimate->save();
        }

        $mail = \Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }
        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }
        $mail->send(new SendEstimateMail($data));

        return [
            'success' => true,
            'type' => 'send',
        ];
    }

    public function getPdfData(Estimate $estimate)
    {
        $taxes = collect();

        if ($estimate->tax_per_item === 'YES') {
            foreach ($estimate->items as $item) {
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

        $estimateTemplate = Estimate::find($estimate->id)->template_name;

        $company = Company::find($estimate->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);
        $customFields = CustomField::where('model_type', 'Item')->get();

        App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'estimate' => $estimate,
            'customFields' => $customFields,
            'logo' => $logo ?? null,
            'company_address' => $estimate->getCompanyAddress(),
            'shipping_address' => $estimate->getCustomerShippingAddress(),
            'billing_address' => $estimate->getCustomerBillingAddress(),
            'notes' => $estimate->getNotes(),
            'taxes' => $taxes,
        ]);

        $template = PdfTemplateUtils::findFormattedTemplate('estimate', $estimateTemplate, '');
        $templatePath = $template['custom'] ? sprintf('pdf_templates::estimate.%s', $estimateTemplate) : sprintf('app.pdf.estimate.%s', $estimateTemplate);

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath);
    }

    public function clone(Estimate $estimate): Estimate
    {
        $date = Carbon::now();

        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
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
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $newEstimate->unique_hash = Hashids::connection(Estimate::class)->encode($newEstimate->id);
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

            $newEstimate->addCustomFields($customFields);
        }

        return $newEstimate;
    }

    public function convertToInvoice(Estimate $estimate): Invoice
    {
        $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

        $invoiceDate = Carbon::now();
        $dueDate = null;

        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $estimate->company_id
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $estimate->company_id
            ));
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $templateName = $estimate->getInvoiceTemplateName();
        $exchangeRate = $estimate->exchange_rate;

        $invoice = Invoice::create([
            'creator_id' => Auth::id(),
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $serial->getNextNumber(),
            'customer_id' => $estimate->customer_id,
            'company_id' => $estimate->company_id,
            'template_name' => $templateName,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
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
            'base_discount_val' => $estimate->discount_val * $exchangeRate,
            'base_sub_total' => $estimate->sub_total * $exchangeRate,
            'base_total' => $estimate->total * $exchangeRate,
            'base_tax' => $estimate->tax * $exchangeRate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        $this->documentItemService->createItems($invoice, $estimate->items->toArray());

        if ($estimate->taxes) {
            $this->documentItemService->createTaxes($invoice, $estimate->taxes->toArray());
        }

        $estimate->checkForEstimateConvertAction();

        return Invoice::find($invoice->id);
    }

    public function changeStatus(Estimate $estimate, string $status): void
    {
        $estimate->update(['status' => $status]);
    }
}
