<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Silber\Bouncer\Database\Role;

/**
 * Validates the staff-account form, which serves both filing a new member and
 * editing one that already exists.
 *
 * The two verbs part company in exactly two places: on an edit the uniqueness
 * check steps over the row being edited, and the password turns optional so a
 * form saved with the field left alone keeps the hash already on file.
 *
 * The address is unique across the whole installation rather than within the
 * company, so a collision tells one tenant that an address is already spoken
 * for somewhere else entirely. Kept as it stands.
 *
 * The membership list may only name companies the caller owns, each with a
 * role that exists in that company. A member who also belongs to a company the
 * caller does not own keeps their email and password: changing either would
 * hand the caller that account, and with it the other company.
 */
class MemberRequest extends FormRequest
{
    /** The columns copied out of the payload onto the account row. */
    private const ACCOUNT_FIELDS = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The member policy, run here as well as in the controller so that a
     * caller who may not touch the member is refused before the payload is
     * weighed at all.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof User
            ? $this->user()->can('update', $member)
            : $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $editing = $this->getMethod() == 'PUT';

        $address = Rule::unique('users');

        if ($editing) {
            $address->ignore($this->member);
        }

        return [
            'name' => ['required'],
            'email' => ['required', new IdnEmail, $address],
            'phone' => ['nullable'],
            'password' => $editing ? ['nullable', 'min:8'] : ['required', 'min:8'],
            'companies' => ['required', 'array', 'min:1'],
            'companies.*.id' => ['required', 'integer', 'distinct', Rule::in($this->managedCompanyIds())],
            'companies.*.role' => ['required', 'string'],
        ];
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

                if ($this->changesCredentialsOfSharedMember()) {
                    $validator->errors()->add('email', 'This member also belongs to a company you do not own, so their email and password cannot be changed here.');
                }
            },
        ];
    }

    /**
     * The companies the caller owns: the only ones a member can be filed into,
     * moved between or taken out of.
     *
     * @return array<int, int>
     */
    public function managedCompanyIds(): array
    {
        return Company::query()
            ->where('owner_id', $this->user()->id)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function changesCredentialsOfSharedMember(): bool
    {
        $member = $this->route('member');

        if ($this->getMethod() !== 'PUT' || $member === null) {
            return false;
        }

        $belongsElsewhere = $member->companies()
            ->whereNotIn('companies.id', $this->managedCompanyIds())
            ->exists();

        return $belongsElsewhere
            && (filled($this->input('password')) || strcasecmp((string) $this->input('email'), (string) $member->email) !== 0);
    }

    /**
     * The account row on its own, stamped with whoever is filing it.
     *
     * On an edit the stamp is written again, so the column records the last
     * person to save the form rather than the one who opened the account.
     *
     * @return array<string, mixed>
     */
    public function getUserPayload()
    {
        return collect($this->validated())
            ->only(self::ACCOUNT_FIELDS)
            ->reject(fn (mixed $value, string $field): bool => $field === 'password' && blank($value))
            ->merge(['creator_id' => $this->user()->id])
            ->toArray();
    }
}
