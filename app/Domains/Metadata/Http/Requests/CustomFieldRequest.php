<?php

namespace App\Domains\Metadata\Http\Requests;

use App\Domains\Metadata\Application\CustomFieldModelCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A custom-field definition as the admin screen submits it.
 *
 * `model_type` is checked against the catalogue, which the editor's dropdown
 * is also built from, so the two cannot drift and an unknown model can no
 * longer reach the column. The default answer never appears below: which
 * value column it belongs in follows from `type`, so the controller reads it
 * off the request untouched and lets the service place it.
 */
class CustomFieldRequest extends FormRequest
{
    /**
     * Every caller is welcome here; the controller is what consults the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'label' => ['required'],
            'model_type' => ['required', Rule::in(app(CustomFieldModelCatalog::class)->keys())],
            'order' => ['required'],
            'type' => ['required'],
            'is_required' => ['required', 'boolean'],
            'options' => ['array', 'nullable'],
            'placeholder' => ['string', 'nullable'],
        ];
    }
}
