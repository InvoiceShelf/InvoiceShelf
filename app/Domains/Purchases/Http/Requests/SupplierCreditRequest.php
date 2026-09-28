<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierCreditRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for the company named in the request header.
     */
    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    /**
     * A bill's rules without a due date or custom fields, plus the optional
     * link to the bill or expense the credit is taken from.
     */
    public static function rulesFor(int $companyId): array
    {
        $rules = BillRequest::rulesFor($companyId);
        unset($rules['due_date']);

        foreach (array_keys($rules) as $key) {
            if (str_starts_with($key, 'customFields')) {
                unset($rules[$key]);
            }
        }

        $rules['customFields'] = ['prohibited'];
        $rules['status'] = ['sometimes', Rule::in(['OPEN'])];
        $rules['source_amount'] = [
            'required_with:source_expense_id',
            'nullable',
            'integer',
            'min:1',
            'max:999999999999',
        ];
        $rules['source_bill_id'] = [
            'nullable',
            'integer',
            'prohibits:source_expense_id',
            Rule::exists('bills', 'id')->where('company_id', $companyId),
        ];
        $rules['source_expense_id'] = [
            'nullable',
            'integer',
            'prohibits:source_bill_id',
            Rule::exists('expenses', 'id')->where('company_id', $companyId),
        ];
        $rules['items.*.source_bill_item_id'] = ['nullable', 'integer', 'distinct'];

        // Linked credits copy their source's monetary and tax snapshots.
        return $rules;
    }
}
