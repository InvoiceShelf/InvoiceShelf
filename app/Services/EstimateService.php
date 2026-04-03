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
use App\Services\Pdf\PdfTemplateUtils;
use Illuminate\Http\Request;

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
}
