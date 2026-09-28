<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    public static function rulesFor(int $companyId, bool $required = true): array
    {
        return [
            'allocations' => [$required ? 'present' : 'sometimes', 'array', 'list', 'max:200'],
            'allocations.*.bill_id' => ['required', 'integer', 'distinct', Rule::exists('bills', 'id')->where('company_id', $companyId)],
            'allocations.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];
    }
}
