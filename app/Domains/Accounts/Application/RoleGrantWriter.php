<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use Illuminate\Support\Facades\DB;
use LogicException;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models;
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
        [$grant, $revoke] = $this->grantPlan($abilities);

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
     * Sync a global role against null-scoped ability and permission rows.
     *
     * Bouncer's removeOnce() removes the filter, so its ability lookup can
     * reuse another company's ability row. Global roles must instead bind to
     * the explicit null-scope catalogue rows so they work in every company.
     *
     * @param  iterable<string>  $abilities
     */
    public function syncGlobal(Role $role, iterable $abilities): void
    {
        if ($role->scope !== null) {
            throw new LogicException("Role [{$role->name}] belongs to a company, so its grants cannot be written globally.");
        }

        $wanted = array_flip(is_array($abilities) ? $abilities : iterator_to_array($abilities, false));
        $grant = [];
        $revoke = [];

        foreach ($this->catalog->all() as $entry) {
            if (isset($wanted[$entry['ability']])) {
                $grant[] = $entry;
            } else {
                $revoke[] = $entry;
            }
        }

        BouncerFacade::scope()->removeOnce(function () use ($role, $grant, $revoke): void {
            $this->deleteScopedGlobalPermissions($role);

            $grantIds = array_map(fn (array $entry): int => $this->globalAbility($entry)->id, $grant);
            $revokeIds = array_values(array_filter(array_map(fn (array $entry): ?int => $this->globalAbilityId($entry), $revoke)));

            if ($revokeIds !== []) {
                $this->globalPermissionQuery($role)
                    ->where('forbidden', false)
                    ->whereIn('ability_id', $revokeIds)
                    ->delete();
            }

            if ($grantIds === []) {
                return;
            }

            $existing = $this->globalPermissionQuery($role)
                ->where('forbidden', false)
                ->whereIn('ability_id', $grantIds)
                ->pluck('ability_id')
                ->all();

            $rows = array_map(fn (int $abilityId): array => [
                'ability_id' => $abilityId,
                'entity_id' => $role->id,
                'entity_type' => $role->getMorphClass(),
                'forbidden' => false,
                'scope' => null,
            ], array_values(array_diff($grantIds, $existing)));

            if ($rows !== []) {
                DB::table('permissions')->insert($rows);
            }
        });

        BouncerFacade::refresh();
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

    /**
     * @return array{array<string, list<string>>, array<string, list<string>>}
     */
    private function grantPlan(iterable $abilities): array
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

        return [$grant, $revoke];
    }

    private function globalAbility(array $entry)
    {
        if ($id = $this->globalAbilityId($entry)) {
            return Models::ability()->newQueryWithoutScopes()->findOrFail($id);
        }

        $model = $entry['model'] ?? null;
        $abilityClass = get_class(Models::ability());

        return $model === null
            ? Models::ability()->create(['name' => $entry['ability']])
            : $abilityClass::createForModel($model, ['name' => $entry['ability']]);
    }

    private function globalAbilityId(array $entry): ?int
    {
        $query = Models::ability()->newQueryWithoutScopes()
            ->where('name', $entry['ability'])
            ->whereNull('scope')
            ->where('only_owned', false);

        $model = $entry['model'] ?? null;

        if ($model === null) {
            $query->whereNull('entity_type')->whereNull('entity_id');
        } else {
            $abilityClass = get_class(Models::ability());
            $template = $abilityClass::makeForModel($model, ['name' => $entry['ability']]);
            $query->where('entity_type', $template->entity_type)
                ->whereNull('entity_id');
        }

        return $query->value('id');
    }

    private function globalPermissionQuery(Role $role)
    {
        return DB::table('permissions')
            ->where('entity_type', $role->getMorphClass())
            ->where('entity_id', $role->id)
            ->whereNull('scope');
    }

    private function deleteScopedGlobalPermissions(Role $role): void
    {
        $permissionIds = DB::table('permissions')
            ->join('abilities', 'abilities.id', '=', 'permissions.ability_id')
            ->where('permissions.entity_type', $role->getMorphClass())
            ->where('permissions.entity_id', $role->id)
            ->whereNull('permissions.scope')
            ->whereNotNull('abilities.scope')
            ->pluck('permissions.id')
            ->all();

        if ($permissionIds !== []) {
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
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
