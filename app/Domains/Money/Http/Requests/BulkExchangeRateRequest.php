<?php

namespace App\Domains\Money\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for the one-shot historical exchange-rate backfill: a list of
 * {id, exchange_rate} pairs. The company setting decides whether the backfill
 * still runs; only the owner, who is also the one who changes the company's
 * currency, may run it.
 */
class BulkExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'currencies' => ['required'],
            'currencies.*.id' => ['required', 'numeric'],
            'currencies.*.exchange_rate' => ['required'],
        ];
    }
}
