<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditNoteRequest extends FormRequest
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
            'credit_note_date' => [
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
            'credit_note_number' => [
                'required',
                Rule::unique('credit_notes')->where('company_id', $this->header('company')),
            ],
            'invoice_id' => [
                'nullable',
            ],
            'notes' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['credit_note_number'] = [
                'required',
                Rule::unique('credit_notes')
                    ->ignore($this->route('credit_note')->id)
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

    public function getCreditNotePayload()
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
