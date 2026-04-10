<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnzipUpdateRequest extends FormRequest
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
            'path' => [
                'required',
                'regex:/^[\.\/\w\-]+$/',
            ],
            'module' => [
                'nullable',
                'string',
            ],
            'module_name' => [
                'required_without:module',
                'string',
            ],
        ];
    }
}
