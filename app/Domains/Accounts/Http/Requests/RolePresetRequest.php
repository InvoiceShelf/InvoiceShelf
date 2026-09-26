<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\RolePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A role preset as the super administrator submits it: a title, and ability
 * names from the catalogue (or ones the preset already holds from a module
 * that is switched off).
 */
class RolePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        /** @var RolePreset|null $preset */
        $preset = $this->route('role_preset');

        $known = [
            ...array_column(app(AbilityCatalog::class)->all(), 'ability'),
            ...($preset?->abilities ?? []),
        ];

        return [
            'title' => ['required', 'string', 'max:100', Rule::unique('role_presets', 'title')->ignore($preset?->id)],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in($known)],
        ];
    }
}
