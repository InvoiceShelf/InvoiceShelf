<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Application\MemberVisibleSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The payload behind writing preferences: one map of option names to values.
 *
 * Values are not constrained, and the currency is guarded by the controller
 * once the books are open. The names are, a little: the mail transport and
 * module settings are refused, because each has an endpoint of its own that
 * validates it.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Owner-only, but the gate that says so runs in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
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
                    $visibility = app(MemberVisibleSettings::class);
                    $refused = array_filter(array_keys($value), fn (int|string $key): bool => $visibility->isPrivate((string) $key));

                    if ($refused !== []) {
                        $fail('These settings have their own page and cannot be changed here: '.implode(', ', $refused).'.');
                    }
                },
            ],
        ];
    }
}
