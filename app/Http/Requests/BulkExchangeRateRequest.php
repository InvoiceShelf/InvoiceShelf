<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only the owner changes the company's currency, so only the owner
        // may run the backfill that a change leaves pending.
        return (bool) $this->user()?->isOwner();
    }

    /**
     * Get the validation rules that apply to the request.
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
