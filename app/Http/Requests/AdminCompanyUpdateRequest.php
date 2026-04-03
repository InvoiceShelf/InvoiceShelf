<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('companies')->ignore($this->route('company')),
            ],
            'owner_id' => [
                'required',
                'exists:users,id',
            ],
            'vat_id' => [
                'nullable',
                'string',
            ],
            'tax_id' => [
                'nullable',
                'string',
            ],
            'address.name' => ['nullable', 'string'],
            'address.address_street_1' => ['nullable', 'string'],
            'address.address_street_2' => ['nullable', 'string'],
            'address.city' => ['nullable', 'string'],
            'address.state' => ['nullable', 'string'],
            'address.country_id' => ['nullable', 'exists:countries,id'],
            'address.zip' => ['nullable', 'string'],
            'address.phone' => ['nullable', 'string'],
        ];
    }
}
