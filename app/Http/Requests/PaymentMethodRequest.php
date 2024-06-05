<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
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
        $data = [
            'name' => [
                'required',
                Rule::unique('payment_methods')
                    ->where('company_id', $this->header('company')),
            ],
        ];

        if ($this->getMethod() == 'PUT') {
            $data['name'] = [
                'required',
                Rule::unique('payment_methods')
                    ->ignore($this->route('payment_method'), 'id')
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $data;
    }

    public function getPaymentMethodPayload()
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
                'type' => PaymentMethod::TYPE_GENERAL,
            ])
            ->toArray();
    }
}
