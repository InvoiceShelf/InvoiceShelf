<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'payment_date' => [
                'required',
            ],
            'customer_id' => [
                'required',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'amount' => [
                'required',
            ],
            'payment_number' => [
                'required',
                Rule::unique('payments')->where('company_id', $this->header('company')),
            ],
            'invoice_id' => [
                'nullable',
            ],
            'payment_method_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['payment_number'] = [
                'required',
                Rule::unique('payments')
                    ->ignore($this->route('payment')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        $maxAmount = $this->maxPayableAmount();

        if ($maxAmount !== null) {
            $rules['amount'] = [
                'required',
                'numeric',
                'max:'.$maxAmount,
            ];
        }

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        return $rules;
    }

    /**
     * The message string IS the translation key here, as everywhere else in the
     * app: the front end maps it to a localized string.
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'payment_amount_exceeds_invoice_due_amount',
        ];
    }

    /**
     * The most that may be paid against the invoice this request names, or null
     * when the payment is not attached to an invoice and so is uncapped.
     *
     * An overpayment used to be accepted and then silently swallowed:
     * PaymentService hands the amount to Invoice::subtractInvoicePayment(),
     * which drives the balance negative, and Invoice::getInvoiceStatusByAmount()
     * returns an empty array for a negative amount, so the status change is
     * never applied and the invoice keeps a stale balance. Partial credit notes
     * shrink the balance and make that easy to hit, so the cap is enforced here,
     * before any of it runs.
     *
     * On an edit of a payment that already belongs to this same invoice its own
     * amount returns to the pool, because PaymentService::update() adds the old
     * amount back before subtracting the new one.
     */
    protected function maxPayableAmount(): ?int
    {
        if (! $this->invoice_id) {
            return null;
        }

        $invoice = Invoice::find($this->invoice_id);

        if (! $invoice) {
            return null;
        }

        $max = (int) $invoice->due_amount;

        $payment = $this->route('payment');

        if ($payment instanceof Payment && (int) $payment->invoice_id === (int) $this->invoice_id) {
            $max += (int) $payment->amount;
        }

        return $max;
    }

    public function getPaymentPayload()
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        return collect($this->validated())
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
                'exchange_rate' => $exchange_rate,
                'base_amount' => $this->amount * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
