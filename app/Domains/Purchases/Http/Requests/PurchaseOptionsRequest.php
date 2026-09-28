<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOptionsRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'custom_field_model' => ['sometimes', Rule::in(['Supplier', 'Bill'])],
        ];
    }
}
