<?php

namespace App\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

class CompanyLogoRequest extends FormRequest
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
            'company_logo' => [
                'nullable',
                new Base64Mime(['gif', 'jpg', 'png']),
            ],
        ];
    }
}
