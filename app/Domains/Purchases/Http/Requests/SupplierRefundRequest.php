<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor((int) $this->header('company'));
    }

    public static function rulesFor(int $companyId): array
    {
        return [
            'supplier_payment_id' => ['nullable', 'required_without:supplier_credit_id', 'prohibits:supplier_credit_id', 'integer', Rule::exists('supplier_payments', 'id')->where('company_id', $companyId)],
            'supplier_credit_id' => ['nullable', 'required_without:supplier_payment_id', 'prohibits:supplier_payment_id', 'integer', Rule::exists('supplier_credits', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
