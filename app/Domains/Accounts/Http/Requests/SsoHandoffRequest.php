<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a single sign-on for the mobile app (SsoHandoffController).
 */
class SsoHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The device's name, which the access token is named after, as with the
     * password sign-in.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }
}
