<?php

namespace App\Platform\Operations\Http\Requests;

use App\Platform\Operations\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters for a single-option read from the settings store.
 */
class GetSettingRequest extends FormRequest
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
     * The option name to look up: a shell setting, since the rest of the
     * store holds credentials among other things. It is echoed back as the
     * response key.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', Rule::in(Setting::SHELL_SETTINGS)],
        ];
    }
}
