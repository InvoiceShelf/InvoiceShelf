<?php

namespace App\Platform\Notifications\Http\Requests;

use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The owner's choices for the company, as `{type: {enabled, bell, mail}}`
 * for the types they changed. Platform notices belong to no company.
 */
class CompanyNotificationDefaultsRequest extends FormRequest
{
    /**
     * The controller checks the company gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_keys(array_filter(
            app(NotificationCatalogue::class)->all(),
            fn (NotificationType $type): bool => ! $type->platform,
        ));

        return [
            'defaults' => ['required', 'array'],
            'defaults.*' => ['array:enabled,bell,mail'],
            'defaults.*.enabled' => ['sometimes', 'boolean'],
            'defaults.*.bell' => ['sometimes', 'boolean'],
            'defaults.*.mail' => ['sometimes', 'boolean'],
            'keys' => ['nullable', 'array'],
            'keys.*' => [Rule::in($types)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['keys' => array_keys((array) $this->input('defaults', []))]);
    }

    /**
     * @return array<string, array{enabled?: bool, bell?: bool, mail?: bool}>
     */
    public function choices(): array
    {
        return array_map(
            fn (array $choice): array => array_map('boolval', $choice),
            (array) $this->validated('defaults'),
        );
    }
}
