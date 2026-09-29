<?php

namespace App\Platform\Notifications\Http\Requests;

use App\Platform\Notifications\NotificationCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A person's choices, as `{type: {bell, mail}}` for the types they changed.
 */
class NotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_keys(app(NotificationCatalogue::class)->all());

        return [
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array:bell,mail'],
            'preferences.*.bell' => ['sometimes', 'boolean'],
            'preferences.*.mail' => ['sometimes', 'boolean'],
            'keys' => ['nullable', 'array'],
            'keys.*' => [Rule::in($types)],
        ];
    }

    /**
     * Check the type names too, which `preferences.*` rules cannot reach.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['keys' => array_keys((array) $this->input('preferences', []))]);
    }

    /**
     * @return array<string, array{bell?: bool, mail?: bool}>
     */
    public function choices(): array
    {
        return array_map(
            fn (array $choice): array => array_map('boolval', $choice),
            (array) $this->validated('preferences'),
        );
    }
}
