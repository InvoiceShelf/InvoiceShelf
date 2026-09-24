<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Support\MoneyConversion;
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
                Rule::exists('customers', 'id')->where('company_id', $this->header('company')),
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
            // The invoice is loaded and its balance changed without any further
            // check, so it must belong to the company the request is for.
            'invoice_id' => [
                'nullable',
                Rule::exists('invoices', 'id')->where('company_id', $this->header('company')),
            ],
            'payment_method_id' => [
                'nullable',
                Rule::exists('payment_methods', 'id')->where('company_id', $this->header('company')),
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
                'base_amount' => MoneyConversion::toBaseMinor($this->amount, $exchange_rate),
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
