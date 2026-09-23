<?php

namespace App\Http\Requests;

use App\Rules\SafeRemoteUrl;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateProviderRequest extends FormRequest
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
            'driver' => [
                'required',
            ],
            'key' => [
                'required',
            ],
            'currencies' => [
                'nullable',
            ],
            'currencies.*' => [
                'nullable',
            ],
            'driver_config' => [
                'nullable',
            ],
            'driver_config.url' => [
                'nullable',
                'string',
                'url',
                new SafeRemoteUrl,
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];

        return $rules;
    }

    public function getExchangeRateProviderPayload()
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }
}
