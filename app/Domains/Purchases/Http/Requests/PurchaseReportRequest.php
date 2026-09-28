<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['from_date' => ['required', 'date_format:Y-m-d'], 'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'], 'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $this->header('company'))]];
    }
}
