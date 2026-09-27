<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Models\Quote;

class QuotesRequest extends SalesProposalsRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $this->user()->can($quote ? 'update' : 'create', $quote ?? Quote::class);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'quote_number' => [...parent::rules()['quote_number'], 'string', 'max:255'],
            'quote_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d'],
            // Sending is a separate, separately-authorized endpoint.
            'quoteSend' => ['prohibited'],
        ]);
    }

    protected function documentKind(): string
    {
        return 'quote';
    }

    public function getQuotePayload(): array
    {
        return $this->getProposalPayload();
    }
}
