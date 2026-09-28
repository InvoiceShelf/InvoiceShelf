<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Domains\Purchases\Application\PurchaseCustomFields;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[BodyParameter('template.customFields.*.value', description: 'Answer matching the field type; null clears an optional answer.', type: 'string|float|bool|null')]
class RecurringCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    public function withValidator(Validator $validator): void
    {
        // The service validates the complete template against its BILL or EXPENSE schema.
        $validator->excludeUnvalidatedArrayKeys = false;
    }

    public static function rulesFor(int $companyId): array
    {
        return [
            ...PurchaseCustomFields::answerRules($companyId, 'Bill', 'template.customFields'),
            'name' => ['required', 'string', 'max:255'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'mode' => ['required', Rule::in(['BILL', 'EXPENSE'])],
            'frequency' => ['required', Rule::in(['DAY', 'WEEK', 'MONTH', 'YEAR'])],
            'interval' => ['required', 'integer', 'between:1,120'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'max_occurrences' => ['nullable', 'integer', 'between:1,10000'],
            'due_days' => ['required', 'integer', 'between:0,3650'],
            'auto_record_paid' => ['required', 'boolean'],
            'template' => ['required', 'array'],
        ];
    }
}
