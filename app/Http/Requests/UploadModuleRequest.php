<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadModuleRequest extends FormRequest
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
            'avatar' => [
                'required',
                'file',
                'mimes:zip',
                'max:20000',
            ],
            'module' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
