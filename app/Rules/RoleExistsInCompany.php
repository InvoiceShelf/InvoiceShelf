<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Silber\Bouncer\Database\Role;

/**
 * A membership's role (`companies.N.role`) names a role that exists in the
 * company of the same entry (`companies.N.id`).
 *
 * Checked past the Bouncer scope, since the request may be acting in another
 * company or in none. Without it, Bouncer would create a missing role, with
 * no abilities, on assignment.
 */
class RoleExistsInCompany implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $companyId = data_get($this->data, Str::beforeLast($attribute, '.').'.id');

        $exists = is_string($value)
            && is_numeric($companyId)
            && Role::query()->withoutGlobalScopes()
                ->where('name', $value)
                ->where('scope', (int) $companyId)
                ->exists();

        if (! $exists) {
            $fail('This role does not exist in that company.');
        }
    }
}
