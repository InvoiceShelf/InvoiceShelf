<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('companies')->ignore($this->header('company'), 'id'),
            ],
            'vat_id' => [
                'nullable',
            ],
            'tax_id' => [
                'nullable',
            ],
            'address.country_id' => [
                'required',
            ],
        ];
    }

    public function getCompanyPayload()
    {
        return collect($this->validated())
            ->only([
                'name',
                'vat_id',
                'tax_id',
            ])
            ->merge([
                'slug' => Str::slug($this->name),
            ])
            ->toArray();
    }
}
