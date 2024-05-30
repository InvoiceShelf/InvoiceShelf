<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users'),
            ],
            'phone' => [
                'nullable',
            ],
            'password' => [
                'required',
                'min:8',
            ],
            'companies' => [
                'required',
            ],
            'companies.*.id' => [
                'required',
            ],
            'companies.*.role' => [
                'required',
            ],
        ];

        if ($this->getMethod() == 'PUT') {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users')->ignore($this->user),
            ];
            $rules['password'] = [
                'nullable',
                'min:8',
            ];
        }

        return $rules;
    }

    public function getUserPayload()
    {
        return collect($this->validated())
            ->merge([
                'creator_id' => $this->user()->id,
            ])
            ->toArray();
    }
}
