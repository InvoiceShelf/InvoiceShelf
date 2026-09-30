<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use App\Rules\IdnEmail;
use App\Rules\RoleExistsInCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A user as the super administrator creates or edits one in Administration →
 * Users: the account, whether it is a super administrator, and, when sent,
 * every company it belongs to with the role it holds there.
 *
 * Someone who owns a company stays in it as its owner, and nobody can take
 * away their own super administrator access.
 */
class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('companies')) {
            $this->merge(['companies' => $this->normalizeMemberships($this->input('companies'))]);
        }
    }

    public function rules(): array
    {
        $user = $this->editing();

        return [
            'name' => ['required', 'string'],
            'email' => ['required', new IdnEmail, Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string'],
            'password' => $user ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'global_roles' => ['sometimes', 'array'],
            'global_roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('role_presets', 'key')->where(fn ($query) => $query->where('key', '!=', RolePreset::OWNER)),
            ],
            'restricted_company_ids' => ['sometimes', 'array'],
            'restricted_company_ids.*' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'companies' => $user ? ['sometimes', 'array'] : ['present', 'array'],
            'companies.*.id' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'companies.*.roles' => ['required', 'array', 'min:1'],
            'companies.*.roles.*' => ['required', 'string', 'distinct', new RoleExistsInCompany],
            'companies.*.include_global_roles' => ['sometimes', 'boolean'],
            // Keep the old key in the contract so legacy clients receive the
            // same field-specific validation response while roles is canonical.
            'companies.*.role' => ['sometimes', 'required', 'string', new RoleExistsInCompany],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->editing();

                if ($user === null) {
                    if ($this->willBeSuperAdmin()) {
                        return;
                    }

                    $this->validateRestrictedCompanyOverlap($validator, null, $this->submittedCompanyIds());

                    return;
                }

                if ($this->has('is_super_admin') && ! $this->boolean('is_super_admin') && $user->is($this->user())) {
                    $validator->errors()->add('is_super_admin', 'You cannot remove your own super administrator access.');
                }

                if ($this->willBeSuperAdmin()) {
                    return;
                }

                if (! $this->has('companies')) {
                    $this->validateRestrictedCompanyOverlap($validator, $user);

                    return;
                }

                $submitted = $this->submittedMemberships();

                $this->validateRestrictedCompanyOverlap($validator, $user, $this->submittedCompanyIds());

                foreach (Company::query()->where('owner_id', $user->id)->whereIn('id', $user->companies()->pluck('companies.id'))->get() as $owned) {
                    if (! in_array('owner', data_get($submitted->get($owned->id), 'roles', []), true)) {
                        $validator->errors()->add('companies', "{$user->name} owns {$owned->name}, so they stay in it with the Owner role.");
                    }
                }
            },
        ];
    }

    /**
     * The account fields to write; a blank password on an edit keeps the old one.
     *
     * @return array<string, mixed>
     */
    public function accountAttributes(): array
    {
        $attributes = $this->safe()->only(['name', 'email', 'phone']);

        if ($this->filled('password')) {
            $attributes['password'] = $this->input('password');
        }

        if ($this->has('is_super_admin')) {
            $attributes['role'] = $this->boolean('is_super_admin') ? 'super admin' : 'user';
        }

        return $attributes;
    }

    public function willBeSuperAdmin(): bool
    {
        if ($this->has('is_super_admin')) {
            return $this->boolean('is_super_admin');
        }

        return $this->editing()?->isSuperAdmin() ?? false;
    }

    /**
     * @return list<string>|null
     */
    public function globalRoleKeys(): ?array
    {
        if (! $this->has('global_roles')) {
            return null;
        }

        return array_values($this->validated('global_roles', []));
    }

    /**
     * @return list<int>|null
     */
    public function restrictedCompanyIds(): ?array
    {
        if (! $this->has('restricted_company_ids')) {
            return null;
        }

        return array_values(array_map('intval', $this->validated('restricted_company_ids', [])));
    }

    private function editing(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    /**
     * Accept the legacy one-role shape while making roles the canonical form.
     */
    private function normalizeMemberships(mixed $companies): mixed
    {
        if (! is_array($companies)) {
            return $companies;
        }

        return array_map(static function (mixed $membership): mixed {
            if (! is_array($membership) || array_key_exists('roles', $membership)) {
                return $membership;
            }

            if (array_key_exists('role', $membership)) {
                $membership['roles'] = [$membership['role']];
            }

            return $membership;
        }, $companies);
    }

    /**
     * @return Collection<int|string, array<string, mixed>>
     */
    private function submittedMemberships(): Collection
    {
        $companies = $this->input('companies');

        return collect(is_array($companies) ? $companies : [])
            ->filter(fn (mixed $membership): bool => is_array($membership))
            ->keyBy('id');
    }

    /**
     * @return list<int>
     */
    private function submittedCompanyIds(): array
    {
        return $this->submittedMemberships()
            ->keys()
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $submittedCompanyIds
     */
    private function validateRestrictedCompanyOverlap(Validator $validator, ?User $user, ?array $submittedCompanyIds = null): void
    {
        if (! $this->has('restricted_company_ids') && $user === null) {
            return;
        }

        $restrictedIds = $this->has('restricted_company_ids')
            ? (array) $this->input('restricted_company_ids')
            : $user?->restrictedCompanies()->pluck('companies.id')->all() ?? [];

        $restricted = collect($restrictedIds)
            ->map(fn (mixed $id): int => (int) $id);

        if ($restricted->isEmpty()) {
            return;
        }

        $direct = collect($submittedCompanyIds ?? $user?->companies()->pluck('companies.id')->all() ?? [])
            ->map(fn (mixed $id): int => (int) $id);

        if ($restricted->intersect($direct)->isNotEmpty()) {
            $validator->errors()->add('restricted_company_ids', 'A restricted company cannot also be directly assigned to this user.');
        }
    }
}
