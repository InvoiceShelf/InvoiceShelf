<?php

namespace App\Domains\Metadata\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A custom-field definition as the admin screen submits it.
 *
 * Two absences are deliberate. `model_type` is checked for presence only, so
 * any string at all reaches the column — the handful of model names the UI
 * offers is a convention, not a constraint. And the default answer never
 * appears below: which value column it belongs in follows from `type`, so the
 * controller reads it off the request untouched and lets the service place it.
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
            'model_type' => ['required'],
            'order' => ['required'],
            'type' => ['required'],
            'is_required' => ['required', 'boolean'],
            'options' => ['array', 'nullable'],
            'placeholder' => ['string', 'nullable'],
        ];
    }
}
