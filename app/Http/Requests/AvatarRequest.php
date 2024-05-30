<?php

namespace App\Http\Requests;

use App\Rules\Base64Mime;
use Illuminate\Foundation\Http\FormRequest;

class AvatarRequest extends FormRequest
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
            'admin_avatar' => [
                'nullable',
                'file',
                'mimes:gif,jpg,png',
                'max:20000',
            ],
            'avatar' => [
                'nullable',
                new Base64Mime(['gif', 'jpg', 'png']),
            ],
        ];
    }
}
