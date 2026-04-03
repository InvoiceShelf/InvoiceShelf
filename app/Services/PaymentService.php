<?php

namespace App\Services;

use App\Facades\Hashids;
use App\Mail\SendPaymentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PaymentService
{
    public function create(Request $request): Payment
    {
        $data = $request->getPaymentPayload();

        if ($request->invoice_id) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoicePayment($request->amount);
        }

        $payment = Payment::create($data);
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);

        $serial = (new SerialNumberFormatter)
            ->setModel($payment)
            ->setCompany($payment->company_id)
            ->setCustomer($payment->customer_id)
            ->setNextNumbers();

        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $payment['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($payment);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $payment->addCustomFields($customFields);
        }

        return Payment::with([
            'customer',
            'invoice',
            'paymentMethod',
            'fields',
        ])->find($payment->id);
    }

    public function update(Payment $payment, Request $request): Payment
    {
        $data = $request->getPaymentPayload();

        if ($request->invoice_id && (! $payment->invoice_id || $payment->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoicePayment($request->amount);
        }

        if ($payment->invoice_id && (! $request->invoice_id || $payment->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($payment->invoice_id);
            $invoice->addInvoicePayment($payment->amount);
        }

        if ($payment->invoice_id && $payment->invoice_id === $request->invoice_id && $request->amount !== $payment->amount) {
            $invoice = Invoice::find($payment->invoice_id);
            $invoice->addInvoicePayment($payment->amount);
            $invoice->subtractInvoicePayment($request->amount);
        }

        $serial = (new SerialNumberFormatter)
            ->setModel($payment)
            ->setCompany($payment->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($payment->id)
            ->setNextNumbers();

        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
        $payment->update($data);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($payment);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $payment->updateCustomFields($customFields);
        }

        return Payment::with([
            'customer',
            'invoice',
            'paymentMethod',
        ])->find($payment->id);
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $payment = Payment::find($id);

            if ($payment->invoice_id != null) {
                $invoice = Invoice::find($payment->invoice_id);
                $invoice->due_amount = ((int) $invoice->due_amount + (int) $payment->amount);

                if ($invoice->due_amount == $invoice->total) {
                    $invoice->paid_status = Invoice::STATUS_UNPAID;
                } else {
                    $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                }

                $invoice->status = $invoice->getPreviousStatus();
                $invoice->save();
            }

            $payment->delete();
        }

        return true;
    }

    public function sendPaymentData(Payment $payment, array $data): array
    {
        $data['payment'] = $payment->toArray();
        $data['user'] = $payment->customer->toArray();
        $data['company'] = Company::find($payment->company_id);
        $data['body'] = $payment->getEmailBody($data['body']);
        $data['attach']['data'] = ($payment->getEmailAttachmentSetting()) ? $this->getPdfData($payment) : null;

        return $data;
    }

    public function send(Payment $payment, array $data): array
    {
        $data = $this->sendPaymentData($payment, $data);

        CompanyMailConfigService::apply($payment->company_id);

        $mail = \Mail::to($data['to']);
        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }
        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }
        $mail->send(new SendPaymentMail($data));

        return [
            'success' => true,
        ];
    }

    public function getPdfData(Payment $payment)
    {
        $company = Company::find($payment->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        \App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'payment' => $payment,
            'company_address' => $payment->getCompanyAddress(),
            'billing_address' => $payment->getCustomerBillingAddress(),
            'notes' => $payment->getNotes(),
            'logo' => $logo ?? null,
        ]);

        if (request()->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return PDF::loadView('app.pdf.payment.payment');
    }

    public function generateFromTransaction($transaction): Payment
    {
        $invoice = Invoice::find($transaction->invoice_id);

        $serial = (new SerialNumberFormatter)
            ->setModel(new Payment)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $data['payment_number'] = $serial->getNextNumber();
        $data['payment_date'] = Carbon::now();
        $data['amount'] = $invoice->total;
        $data['invoice_id'] = $invoice->id;
        $data['payment_method_id'] = request()->payment_method_id;
        $data['customer_id'] = $invoice->customer_id;
        $data['exchange_rate'] = $invoice->exchange_rate;
        $data['base_amount'] = $data['amount'] * $data['exchange_rate'];
        $data['currency_id'] = $invoice->currency_id;
        $data['company_id'] = $invoice->company_id;
        $data['transaction_id'] = $transaction->id;

        $payment = Payment::create($data);
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        $invoice->subtractInvoicePayment($invoice->total);

        return $payment;
    }
}
