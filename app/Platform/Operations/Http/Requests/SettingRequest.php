<?php

namespace App\Platform\Operations\Http\Requests;

use App\Platform\Operations\Models\Setting;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for a bulk write to the settings store.
 */
class SettingRequest extends FormRequest
{
    /**
     * Access is decided by the `manage settings` ability in the controller,
     * so the request itself lets everything through.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A map of options to write, every one of them a shell setting. Anything
     * else in the store has an endpoint of its own, or is not for writing.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'bail',
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $refused = array_diff(array_keys($value), Setting::SHELL_SETTINGS);

                    if ($refused !== []) {
                        $fail('These settings cannot be changed here: '.implode(', ', $refused).'.');
                    }
                },
            ],
        ];
    }
}
