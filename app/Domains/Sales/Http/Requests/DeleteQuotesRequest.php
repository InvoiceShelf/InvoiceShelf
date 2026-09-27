<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of the bulk quote removal endpoint: a list of ids, each of which
 * has to be a quote. Company scoping is applied by the controller when it
 * resolves the ids, not here.
 */
class DeleteQuotesRequest extends FormRequest
{
    /**
     * The ability is checked in the controller.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete multiple quotes');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('quotes', 'id')->where('company_id', $this->header('company'))],
        ];
    }
}
