<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateLogRequest extends FormRequest
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
        return [
            'exchange_rate' => [
                'required',
            ],
            'currency_id' => [
                'required',
            ],
        ];
    }

    public function getExchangeRateLogPayload()
    {
        $companyCurrency = CompanySetting::getSetting(
            'currency',
            $this->header('company')
        );

        if ($this->currency_id !== $companyCurrency) {
            return collect($this->validated())
                ->merge([
                    'company_id' => $this->header('company'),
                    'base_currency_id' => $companyCurrency,
                ])
                ->toArray();
        }
    }
}
