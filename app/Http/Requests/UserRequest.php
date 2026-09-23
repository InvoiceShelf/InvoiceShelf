<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Silber\Bouncer\Database\Role;

/**
 * The companies list may only name companies the caller owns, each with a role
 * that exists in that company. A user who also belongs to a company the caller
 * does not own keeps their email and password: changing either would hand the
 * caller that account, and with it the other company.
 */
class UserRequest extends FormRequest
{
    /**
     * The user policy, run before the payload is weighed at all.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? $this->user()->can('update', $user)
            : $this->user()->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users'),
            ],
            'phone' => [
                'nullable',
            ],
            'password' => [
                'required',
                'min:8',
            ],
            'companies' => [
                'required',
                'array',
                'min:1',
            ],
            'companies.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::in($this->managedCompanyIds()),
            ],
            'companies.*.role' => [
                'required',
                'string',
            ],
        ];

        if ($this->getMethod() == 'PUT') {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users')->ignore($this->user),
            ];
            $rules['password'] = [
                'nullable',
                'min:8',
            ];
        }

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('companies') as $index => $membership) {
                    $exists = Role::withoutGlobalScopes()
                        ->where('name', $membership['role'] ?? null)
                        ->where('scope', $membership['id'] ?? null)
                        ->exists();

                    if (! $exists) {
                        $validator->errors()->add("companies.{$index}.role", 'This role does not exist in that company.');
                    }
                }

                if ($this->changesCredentialsOfSharedUser()) {
                    $validator->errors()->add('email', 'This user also belongs to a company you do not own, so their email and password cannot be changed here.');
                }
            },
        ];
    }

    /**
     * The companies the caller owns: the only ones a user can be filed into,
     * moved between or taken out of.
     *
     * @return array<int, int>
     */
    public function managedCompanyIds(): array
    {
        return Company::query()
            ->where('owner_id', $this->user()->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function changesCredentialsOfSharedUser(): bool
    {
        $user = $this->route('user');

        if ($this->getMethod() !== 'PUT' || ! $user instanceof User) {
            return false;
        }

        $belongsElsewhere = $user->companies()
            ->whereNotIn('companies.id', $this->managedCompanyIds())
            ->exists();

        return $belongsElsewhere
            && (filled($this->input('password')) || strcasecmp((string) $this->input('email'), (string) $user->email) !== 0);
    }

    public function getUserPayload()
    {
        return collect($this->validated())
            ->merge([
                'creator_id' => $this->user()->id,
            ])
            ->toArray();
    }
}
