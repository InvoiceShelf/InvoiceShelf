<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReportRequest extends FormRequest
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
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
        ];
    }
}
