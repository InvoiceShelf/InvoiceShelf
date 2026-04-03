<?php

namespace App\Services;

use App;
use App\Facades\Hashids;
use App\Facades\Pdf;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomField;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\Pdf\PdfTemplateUtils;
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

        if ($request->total >= 0 && $request->total < $totalPaidAmount) {
            throw ValidationException::withMessages([
                'total' => ['total_invoice_amount_must_be_more_than_paid_amount'],
            ]);
        }

        if ($oldTotal != $request->total) {
            $oldTotal = (int) round($request->total) - (int) $oldTotal;
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
        $mail->send(new SendInvoiceMail($data));

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

        $template = PdfTemplateUtils::findFormattedTemplate('invoice', $invoiceTemplate, '');
        $templatePath = $template['custom'] ? sprintf('pdf_templates::invoice.%s', $invoiceTemplate) : sprintf('app.pdf.invoice.%s', $invoiceTemplate);

        if (request()->has('preview')) {
            return view($templatePath);
        }

        return Pdf::loadView($templatePath);
    }
}
