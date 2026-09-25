<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use LogicException;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

/**
 * Makes a company role hold exactly a given set of catalogue abilities.
 *
 * Ability and permission rows live in the company's Bouncer scope, and a
 * request without one (the super administrator's, a console command, a
 * migration) would find another company's ability rows and write permissions
 * nobody can see. So every write runs inside the role's own scope, and a role
 * without one is refused.
 *
 * Abilities are handed to Bouncer a subject model at a time: it still looks
 * each one up, but diffs and attaches them together, which keeps setting up a
 * company's roles to a few dozen queries.
 */
class RoleGrantWriter
{
    public function __construct(private readonly AbilityCatalog $catalog) {}

    /**
     * Grant the catalogue entries named in $abilities and revoke every other
     * catalogue entry. Names outside the catalogue are never touched, so a
     * disabled module's grants survive until it is switched back on.
     *
     * @param  iterable<string>  $abilities
     */
    public function sync(Role $role, iterable $abilities): void
    {
        $wanted = array_flip(is_array($abilities) ? $abilities : iterator_to_array($abilities, false));
        $grant = [];
        $revoke = [];

        foreach ($this->catalog->all() as $entry) {
            $group = $entry['model'] ?? '';

            if (isset($wanted[$entry['ability']])) {
                $grant[$group][] = $entry['ability'];
            } else {
                $revoke[$group][] = $entry['ability'];
            }
        }

        $this->inScopeOf($role, function () use ($role, $grant, $revoke): void {
            foreach ($grant as $model => $names) {
                BouncerFacade::allow($role)->to($names, $model === '' ? null : $model);
            }

            foreach ($revoke as $model => $names) {
                BouncerFacade::disallow($role)->to($names, $model === '' ? null : $model);
            }
        });
    }

    /**
     * Grant the whole catalogue, module abilities included, revoking nothing.
     * The owner role is always handed everything.
     */
    public function grantAll(Role $role): void
    {
        $grant = [];

        foreach ($this->catalog->all() as $entry) {
            $grant[$entry['model'] ?? ''][] = $entry['ability'];
        }

        $this->inScopeOf($role, function () use ($role, $grant): void {
            foreach ($grant as $model => $names) {
                BouncerFacade::allow($role)->to($names, $model === '' ? null : $model);
            }
        });
    }

    private function inScopeOf(Role $role, callable $write): void
    {
        if ($role->scope === null) {
            throw new LogicException("Role [{$role->name}] belongs to no company, so its grants cannot be written.");
        }

        BouncerFacade::scope()->onceTo((int) $role->scope, $write);
        BouncerFacade::refresh();
    }
}
