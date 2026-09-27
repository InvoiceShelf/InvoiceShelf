<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([Quote::STATUS_DRAFT, Quote::STATUS_SENT, Quote::STATUS_VIEWED, Quote::STATUS_ACCEPTED, Quote::STATUS_REJECTED, Quote::STATUS_EXPIRED])]];
    }
}
