<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Rules\IdnEmail;
use App\Rules\RoleExistsInCompany;
use Illuminate\Foundation\Http\FormRequest;
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

    public function rules(): array
    {
        $user = $this->editing();

        return [
            'name' => ['required', 'string'],
            'email' => ['required', new IdnEmail, Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string'],
            'password' => $user ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'is_super_admin' => ['sometimes', 'boolean'],
            'companies' => $user ? ['sometimes', 'array'] : ['present', 'array'],
            'companies.*.id' => ['required', 'integer', 'distinct', Rule::exists('companies', 'id')],
            'companies.*.role' => ['required', 'string', new RoleExistsInCompany],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->editing();

                if ($user === null) {
                    return;
                }

                if ($this->has('is_super_admin') && ! $this->boolean('is_super_admin') && $user->is($this->user())) {
                    $validator->errors()->add('is_super_admin', 'You cannot remove your own super administrator access.');
                }

                if (! $this->has('companies')) {
                    return;
                }

                $submitted = collect((array) $this->input('companies'))->keyBy('id');

                foreach (Company::query()->where('owner_id', $user->id)->whereIn('id', $user->companies()->pluck('companies.id'))->get() as $owned) {
                    if (($submitted[$owned->id]['role'] ?? null) !== 'owner') {
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

    private function editing(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
