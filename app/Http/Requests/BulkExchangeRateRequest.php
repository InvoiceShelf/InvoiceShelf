<?php

namespace InvoiceShelf\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'currencies' => [
                'required',
            ],
            'currencies.*.id' => [
                'required',
                'numeric',
            ],
            'currencies.*.exchange_rate' => [
                'required',
            ],
        ];
    }
}
