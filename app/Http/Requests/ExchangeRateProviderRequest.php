<?php

namespace App\Http\Requests;

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
        $driversWithoutKey = collect(config('invoiceshelf.exchange_rate_drivers'))
            ->where('required_key', false)
            ->pluck('value')
            ->implode(',');

        $rules = [
            'driver' => [
                'required',
            ],
            'key' => [
                'required_unless:driver,'.$driversWithoutKey,
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
            'active' => [
                'nullable',
                'boolean',
            ],
        ];

        return $rules;
    }

    public function getExchangeRateProviderPayload()
    {
        $data = $this->validated();

        if (! array_key_exists('key', $data) || $data['key'] === null) {
            $data['key'] = '';
        }

        return collect($data)
            ->merge([
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }
}
