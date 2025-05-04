<?php

namespace App\Http\Requests;

use App\Models\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxTypeRequest extends FormRequest
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
        $rules = [
            'name' => [
                'required',
                Rule::unique('tax_types')
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('company_id', $this->header('company')),
            ],
            'calculation_type' => [
                'required',
                Rule::in(['percentage', 'fixed']),
            ],
            'percent' => [
                'nullable',
                'numeric',
            ],
            'fixed_amount' => [
                'nullable',
                'numeric',
            ],
            'description' => [
                'nullable',
            ],
            'compound_tax' => [
                'nullable',
            ],
            'collective_tax' => [
                'nullable',
            ],
        ];

        if ($this->isMethod('PUT')) {
            $rules['name'] = [
                'required',
                Rule::unique('tax_types')
                    ->ignore($this->route('tax_type')->id)
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    public function getTaxTypePayload()
    {
        return collect($this->validated())
            ->merge([
                'company_id' => $this->header('company'),
                'type' => TaxType::TYPE_GENERAL,
            ])
            ->toArray();
    }
}
