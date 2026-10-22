<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

/**
 * Resolves company access that comes from explicit memberships and from
 * installation-wide preset assignments.
 */
class UserCompanyAccessService
{
    private const GLOBAL_ROLE_PREFIX = 'global:preset:';

    public function __construct(private readonly RoleGrantWriter $grants) {}

    /**
     * @return EloquentCollection<int, Company>
     */
    public function accessibleCompanies(User $user): EloquentCollection
    {
        if ($user->isSuperAdmin()) {
            return Company::query()->orderBy('name')->get();
        }

        $restricted = $this->restrictedCompanyIds($user);

        if ($this->hasGlobalRoles($user)) {
            return Company::query()
                ->when($restricted !== [], fn ($query) => $query->whereNotIn('id', $restricted))
                ->orderBy('name')
                ->get();
        }

        return $user->companies()
            ->when($restricted !== [], fn ($query) => $query->whereNotIn('companies.id', $restricted))
            ->orderBy('name')
            ->get();
    }

    public function firstAccessibleCompany(User $user): ?Company
    {
        if ($user->isSuperAdmin()) {
            return $user->companies()->orderBy('name')->first()
                ?? Company::query()->orderBy('name')->first();
        }

        return $this->accessibleCompanies($user)->first();
    }

    public function canAccessCompany(User $user, int|Company $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        if ($company instanceof Company ? ! $company->exists : ! Company::query()->whereKey($companyId)->exists()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->isRestrictedFromCompany($user, $companyId)) {
            return false;
        }

        return $this->hasDirectCompany($user, $companyId) || $this->hasGlobalRoles($user);
    }

    public function isRestrictedFromCompany(User $user, int $companyId): bool
    {
        if ($user->isSuperAdmin() || ! Schema::hasTable('user_restricted_companies')) {
            return false;
        }

        return $user->restrictedCompanies()->where('companies.id', $companyId)->exists();
    }

    /**
     * @return list<int>
     */
    public function restrictedCompanyIds(User $user): array
    {
        if ($user->isSuperAdmin() || ! Schema::hasTable('user_restricted_companies')) {
            return [];
        }

        return $user->restrictedCompanies()
            ->pluck('companies.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function hasGlobalRoles(User $user): bool
    {
        if (! Schema::hasTable('user_global_roles')) {
            return false;
        }

        return $user->globalRolePresets()->exists();
    }

    /**
     * @param  list<string>|null  $presetKeys
     * @param  list<int>|null  $restrictedCompanyIds
     */
    public function syncUserAccess(User $user, ?array $presetKeys, ?array $restrictedCompanyIds): void
    {
        if ($presetKeys !== null && Schema::hasTable('user_global_roles')) {
            $presets = RolePreset::query()
                ->whereIn('key', $presetKeys)
                ->where('key', '!=', RolePreset::OWNER)
                ->get();

            foreach ($presets as $preset) {
                $this->syncGlobalRole($preset);
            }

            $user->globalRolePresets()->sync($presets->pluck('id')->all());
            $this->syncBouncerAssignments($user, $presets);
        }

        if ($restrictedCompanyIds !== null && Schema::hasTable('user_restricted_companies')) {
            $user->restrictedCompanies()->sync($restrictedCompanyIds);
        }

        $this->removeUnusedGlobalRoles();

        // Direct database writes bypass Bouncer's assignment conductors, and
        // its cache key includes the active company scope. Refresh all scopes
        // so a prior authorization lookup cannot retain a removed role.
        BouncerFacade::refresh();
    }

    /**
     * Replace only the user's direct role assignments in one company.
     *
     * Bouncer's scoped role sync reads the effective role relation. That
     * relation intentionally includes global roles for users without direct
     * roles, so using it for a write could mistake a global assignment for a
     * company assignment and delete it. Direct assignments are therefore
     * replaced explicitly by their company scope.
     *
     * @param  iterable<string>  $roleNames
     */
    public function replaceCompanyRoles(User $user, int $companyId, iterable $roleNames): void
    {
        $names = collect($roleNames)
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values();

        $roleIds = Role::query()
            ->withoutGlobalScopes()
            ->where('scope', $companyId)
            ->whereIn('name', $names->all())
            ->pluck('id')
            ->all();

        if (count($roleIds) !== $names->count()) {
            throw new \LogicException("One or more roles do not exist in company {$companyId}.");
        }

        DB::table('assigned_roles')
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->id)
            ->where('scope', $companyId)
            ->delete();

        if ($roleIds !== []) {
            DB::table('assigned_roles')->insert(
                array_map(fn (int|string $roleId): array => [
                    'role_id' => $roleId,
                    'entity_id' => $user->id,
                    'entity_type' => $user->getMorphClass(),
                    'scope' => $companyId,
                ], $roleIds),
            );
        }

        // Direct assignments are read through a scope-specific Bouncer cache.
        BouncerFacade::refresh();
    }

    public function ensureCompanyRole(User $user, int $companyId, string $roleName): void
    {
        $roleId = Role::query()
            ->withoutGlobalScopes()
            ->where('scope', $companyId)
            ->where('name', $roleName)
            ->value('id');

        if ($roleId === null) {
            throw new \LogicException("Role [{$roleName}] does not exist in company {$companyId}.");
        }

        $assigned = DB::table('assigned_roles')
            ->where('role_id', $roleId)
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->id)
            ->where('scope', $companyId)
            ->exists();

        if (! $assigned) {
            DB::table('assigned_roles')->insert([
                'role_id' => $roleId,
                'entity_id' => $user->id,
                'entity_type' => $user->getMorphClass(),
                'scope' => $companyId,
            ]);
        }

        BouncerFacade::refresh();
    }

    public function removeCompanyRole(User $user, int $companyId, string $roleName): void
    {
        $roleId = Role::query()
            ->withoutGlobalScopes()
            ->where('scope', $companyId)
            ->where('name', $roleName)
            ->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('assigned_roles')
            ->where('role_id', $roleId)
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->id)
            ->where('scope', $companyId)
            ->delete();

        BouncerFacade::refresh();
    }

    /**
     * Keep the unscoped Bouncer role for a preset aligned with that preset.
     */
    public function syncGlobalRole(RolePreset $preset): void
    {
        if ($preset->isOwner()) {
            return;
        }

        $role = BouncerFacade::scope()->removeOnce(fn () => Role::query()->withoutGlobalScopes()
            ->firstOrCreate(
                ['name' => $this->globalRoleName($preset), 'scope' => null],
                ['title' => $preset->title],
            ));

        if ($role->title !== $preset->title) {
            $role->title = $preset->title;
            $role->save();
        }

        $this->grants->syncGlobal($role, $preset->abilities ?? []);
    }

    public function deleteGlobalRole(RolePreset $preset): void
    {
        Role::query()->withoutGlobalScopes()
            ->where('name', $this->globalRoleName($preset))
            ->whereNull('scope')
            ->get()
            ->each(fn (Role $role) => $role->delete());

        BouncerFacade::refresh();
    }

    public function globalUsage(RolePreset $preset): int
    {
        if (! Schema::hasTable('user_global_roles')) {
            return 0;
        }

        return DB::table('user_global_roles')->where('role_preset_id', $preset->id)->count();
    }

    public function globalRoleName(RolePreset $preset): string
    {
        return self::GLOBAL_ROLE_PREFIX.$preset->key;
    }

    private function removeUnusedGlobalRoles(): void
    {
        Role::query()->withoutGlobalScopes()
            ->where('name', 'like', self::GLOBAL_ROLE_PREFIX.'%')
            ->whereNull('scope')
            ->get()
            ->each(function (Role $role): void {
                $inUse = DB::table('assigned_roles')
                    ->where('role_id', $role->id)
                    ->whereNull('scope')
                    ->exists();

                if (! $inUse) {
                    $role->delete();
                }
            });
    }

    public function hasDirectCompany(User $user, int $companyId): bool
    {
        return $user->companies()->where('companies.id', $companyId)->exists();
    }

    /**
     * Add readable role titles for an Administration users page.
     *
     * Administration requests have no active company scope, so the Bouncer
     * roles relation is not a suitable list-wide summary. Load the scoped
     * assignments for the current page in one query and combine them with the
     * already eager-loaded global preset titles.
     *
     * @param  Collection<int, User>|EloquentCollection<int, User>  $users
     */
    public function attachRoleLabels(Collection|EloquentCollection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $labels = $users->mapWithKeys(function (User $user): array {
            $globalTitles = $user->relationLoaded('globalRolePresets')
                ? $user->globalRolePresets->pluck('title')->all()
                : [];

            return [$user->id => $globalTitles];
        })->all();

        DB::table('assigned_roles')
            ->join('roles', 'assigned_roles.role_id', '=', 'roles.id')
            ->join('user_company', function ($join): void {
                $join->on('user_company.user_id', '=', 'assigned_roles.entity_id')
                    ->on('user_company.company_id', '=', 'assigned_roles.scope');
            })
            ->where('assigned_roles.entity_type', (new User)->getMorphClass())
            ->whereIn('assigned_roles.entity_id', $users->pluck('id')->all())
            ->whereNotNull('assigned_roles.scope')
            ->orderBy('assigned_roles.entity_id')
            ->orderBy('roles.id')
            ->get([
                'assigned_roles.entity_id',
                'roles.title',
                'roles.name',
            ])
            ->each(function (object $assignment) use (&$labels): void {
                $labels[$assignment->entity_id][] = $assignment->title ?: $assignment->name;
            });

        foreach ($users as $user) {
            $user->setAttribute(
                'role_labels',
                array_values(array_unique(array_filter($labels[$user->id] ?? []))),
            );
        }
    }

    /**
     * @param  EloquentCollection<int, RolePreset>|Collection<int, RolePreset>  $presets
     */
    private function syncBouncerAssignments(User $user, EloquentCollection|Collection $presets): void
    {
        $globalRoleIds = Role::query()->withoutGlobalScopes()
            ->whereNull('scope')
            ->where('name', 'like', self::GLOBAL_ROLE_PREFIX.'%')
            ->pluck('id');

        if ($globalRoleIds->isNotEmpty()) {
            DB::table('assigned_roles')
                ->where('entity_type', $user->getMorphClass())
                ->where('entity_id', $user->id)
                ->whereNull('scope')
                ->whereIn('role_id', $globalRoleIds)
                ->delete();
        }

        $rows = Role::query()->withoutGlobalScopes()
            ->whereNull('scope')
            ->whereIn('name', $presets->map(fn (RolePreset $preset): string => $this->globalRoleName($preset))->all())
            ->pluck('id')
            ->map(fn (int $roleId): array => [
                'role_id' => $roleId,
                'entity_id' => $user->id,
                'entity_type' => $user->getMorphClass(),
                'scope' => null,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('assigned_roles')->insert($rows);
        }
    }
}
