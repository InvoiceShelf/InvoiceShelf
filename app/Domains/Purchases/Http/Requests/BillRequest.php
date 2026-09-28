<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Domains\Purchases\Application\PurchaseCustomFields;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[BodyParameter('customFields.*.value', description: 'Answer matching the field type; null clears an optional answer.', type: 'string|float|bool|null')]
class BillRequest extends FormRequest
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

    public static function rulesFor(int $companyId): array
    {
        return [
            ...PurchaseCustomFields::answerRules($companyId, 'Bill'),
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'status' => ['sometimes', Rule::in(['DRAFT', 'OPEN'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tax_included' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:1000'],
            'items.*.expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where('company_id', $companyId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,2'],
            'items.*.price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'items.*.discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_type_ids' => ['sometimes', 'array', 'list', 'max:10'],
            'items.*.tax_type_ids.*' => [
                'integer',
                Rule::exists('tax_types', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', 'GENERAL')
                    ->where('transaction_type', 'purchases'),
            ],
        ];
    }
}
