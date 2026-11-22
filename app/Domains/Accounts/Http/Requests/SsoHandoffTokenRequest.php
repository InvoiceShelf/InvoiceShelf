<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Finishing a single sign-on for the mobile app (SsoHandoffController):
 * the secret the app kept and the code the browser brought back.
 */
class SsoHandoffTokenRequest extends FormRequest
{
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
            'secret' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
