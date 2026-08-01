<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCreditNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The controller authorizes the ability against the invoice being credited.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * "items" is optional: an absent or empty list credits every remaining
     * quantity of the invoice, which is the full reversal. Each supplied line
     * must name a line of THIS invoice, once, with a positive quantity; how
     * much of it is still creditable is a domain invariant and belongs to
     * CreditNoteService, which decides it under a row lock.
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'items' => [
                'sometimes',
                'array',
            ],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('invoice_items', 'id')->where('invoice_id', $this->route('invoice')->id),
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }

    /**
     * The message string IS the translation key here, as everywhere else in the
     * app: the front end maps it to a localized string.
     */
    public function messages(): array
    {
        return [
            'items.*.id.required' => 'credit_item_not_on_invoice',
            'items.*.id.integer' => 'credit_item_not_on_invoice',
            'items.*.id.distinct' => 'credit_item_not_on_invoice',
            'items.*.id.exists' => 'credit_item_not_on_invoice',
            'items.*.quantity.required' => 'credit_quantity_invalid',
            'items.*.quantity.numeric' => 'credit_quantity_invalid',
            'items.*.quantity.gt' => 'credit_quantity_invalid',
        ];
    }
}
