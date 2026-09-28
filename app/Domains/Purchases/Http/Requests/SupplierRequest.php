<?php

namespace App\Domains\Purchases\Http\Requests;

use App\Domains\Purchases\Application\PurchaseCustomFields;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[BodyParameter('customFields.*.value', description: 'Answer matching the field type; null clears an optional answer.', type: 'string|float|bool|null')]
class SupplierRequest extends FormRequest
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
            ...PurchaseCustomFields::answerRules($companyId, 'Supplier'),
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'expense_category_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')->where('company_id', $companyId),
            ],
            'payment_terms' => ['required', 'integer', 'between:0,3650'],
            'addresses' => ['sometimes', 'array', 'list', 'max:5'],
            'addresses.*' => ['array:address_street_1,address_street_2,city,state,zip,country_id'],
            'addresses.*.address_street_1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_street_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.zip' => ['nullable', 'string', 'max:50'],
            'addresses.*.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'notes' => ['nullable', 'string', 'max:10000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
