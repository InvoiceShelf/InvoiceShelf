<?php

namespace App\Domains\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The id list handed to the bulk payment removal.
 */
class DeletePaymentsRequest extends FormRequest
{
    /**
     * The bulk-delete permission is checked by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every id has to name a payment that exists. Narrowing the set to the
     * active company is left to the controller, so an id belonging to another
     * company passes validation and is then dropped from the delete.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required'],
            'ids.*' => ['required', Rule::exists('customer_payments', 'id')],
        ];
    }
}
