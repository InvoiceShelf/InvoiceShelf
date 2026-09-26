<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Application\Exceptions\RolePresetInUse;
use App\Domains\Accounts\Application\Exceptions\RolePresetLocked;
use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Models;
use Silber\Bouncer\Database\Role;

/**
 * Role presets: roles the super administrator defines once, which every
 * company holds as a copy it can assign but not change.
 *
 * A preset's copies follow it: creating a preset hands it to every company,
 * editing one rewrites every copy, and a new company is given all of them.
 * The Owner preset is the `owner` role every company already has; it always
 * holds the whole catalogue and cannot be changed or removed.
 */
class RolePresetService
{
    public function __construct(
        private readonly AbilityCatalog $catalog,
        private readonly RoleGrantWriter $grants,
    ) {}

    /**
     * The presets, Owner first, then in the order they were made. The Owner
     * preset is always there, stored or not, so a company is never set up
     * without an owner role holding every ability.
     *
     * @return Collection<int, RolePreset>
     */
    public function all(): Collection
    {
        $presets = RolePreset::query()->orderBy('id')->get();

        if (! $presets->contains(fn (RolePreset $preset) => $preset->isOwner())) {
            $presets->prepend(new RolePreset(['key' => RolePreset::OWNER, 'title' => 'Owner', 'abilities' => null]));
        }

        return $presets
            ->sortBy(fn (RolePreset $preset) => $preset->isOwner() ? 0 : 1, SORT_NUMERIC)
            ->values();
    }

    /**
     * Give a company its copy of every preset, with exactly the preset's
     * abilities. Safe to repeat.
     */
    public function syncCompany(int $companyId): void
    {
        foreach ($this->all() as $preset) {
            $this->syncCopy($preset, $companyId);
        }
    }

    /**
     * Bring every company's copy of one preset in line with it.
     */
    public function syncPreset(RolePreset $preset): void
    {
        DB::transaction(function () use ($preset): void {
            foreach (Company::query()->lazyById() as $company) {
                $this->syncCopy($preset, $company->id);
            }
        });
    }

    /**
     * Every preset in every company: the upgrade and the repair command.
     */
    public function syncAll(): void
    {
        foreach (Company::query()->lazyById() as $company) {
            $this->syncCompany($company->id);
        }
    }

    /**
     * @param  list<string>  $abilities
     */
    public function create(string $title, array $abilities): RolePreset
    {
        $preset = RolePreset::query()->create([
            'key' => $this->freshKey($title),
            'title' => $title,
            'abilities' => $this->settle($abilities),
        ]);

        $this->syncPreset($preset);

        return $preset;
    }

    /**
     * Retitle a preset and give it a new set of abilities, in every company.
     * Abilities it holds from a module that is switched off are kept.
     *
     * @param  list<string>  $abilities
     */
    public function update(RolePreset $preset, string $title, array $abilities): RolePreset
    {
        if ($preset->isOwner()) {
            throw new RolePresetLocked;
        }

        $catalogue = $this->catalogueNames();
        $kept = array_values(array_diff($preset->abilities ?? [], $catalogue));

        $preset->update([
            'title' => $title,
            'abilities' => array_values(array_unique([...$this->settle($abilities), ...$kept])),
        ]);

        $this->syncPreset($preset);

        return $preset;
    }

    /**
     * Remove a preset and its copy in every company, unless a current member
     * holds it or a pending invitation offers it.
     */
    public function delete(RolePreset $preset): void
    {
        if ($preset->isOwner()) {
            throw new RolePresetLocked;
        }

        ['members' => $members, 'invitations' => $invitations] = $this->usage($preset);

        if ($members > 0 || $invitations > 0) {
            throw new RolePresetInUse($members, $invitations);
        }

        DB::transaction(function () use ($preset): void {
            foreach ($this->copies($preset) as $role) {
                BouncerFacade::scope()->onceTo((int) $role->scope, fn () => $role->delete());
            }

            $preset->delete();
        });

        BouncerFacade::refresh();
    }

    /**
     * How many current members hold a preset and how many pending invitations
     * offer it, across every company. An assignment left behind by someone
     * who has since left the company does not count.
     *
     * @return array{members: int, invitations: int}
     */
    public function usage(RolePreset $preset): array
    {
        $roleIds = $this->copies($preset)->pluck('id');

        if ($roleIds->isEmpty()) {
            return ['members' => 0, 'invitations' => 0];
        }

        $members = Models::query('assigned_roles')
            ->join('user_company', function ($join): void {
                $join->on('user_company.user_id', '=', 'assigned_roles.entity_id')
                    ->on('user_company.company_id', '=', 'assigned_roles.scope');
            })
            ->whereIn('assigned_roles.role_id', $roleIds)
            ->where('assigned_roles.entity_type', (new User)->getMorphClass())
            ->count();

        $invitations = CompanyInvitation::query()->pending()->whereIn('role_id', $roleIds)->count();

        return ['members' => $members, 'invitations' => $invitations];
    }

    /**
     * A module was switched on: hand its abilities to every copy of a preset
     * that lists them. The owner copies already have them.
     *
     * @param  list<string>  $abilities
     */
    public function grantListed(array $abilities): void
    {
        foreach ($this->all() as $preset) {
            if ($preset->isOwner() || array_intersect($abilities, $preset->abilities ?? []) === []) {
                continue;
            }

            $this->syncPreset($preset);
        }
    }

    /**
     * Every company's copy of a preset, read past the Bouncer scope.
     *
     * @return Collection<int, Role>
     */
    public function copies(RolePreset $preset): Collection
    {
        return Role::query()->withoutGlobalScopes()
            ->where('name', $preset->roleName())
            ->whereNotNull('scope')
            ->get();
    }

    private function syncCopy(RolePreset $preset, int $companyId): void
    {
        $role = Role::query()->withoutGlobalScopes()
            ->where('name', $preset->roleName())
            ->where('scope', $companyId)
            ->first();

        if ($role === null) {
            $role = BouncerFacade::scope()->onceTo($companyId, fn () => Role::query()->create([
                'name' => $preset->roleName(),
                'title' => $preset->title,
            ]));
        } elseif ($role->title !== $preset->title) {
            $role->title = $preset->title;
            $role->save();
        }

        $preset->isOwner()
            ? $this->grants->grantAll($role)
            : $this->grants->sync($role, $preset->abilities ?? []);
    }

    /**
     * The submitted abilities that exist in the catalogue, with everything
     * they depend on, in catalogue order.
     *
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function settle(array $abilities): array
    {
        $entries = collect($this->catalog->all())->keyBy('ability');
        $chosen = [];
        $queue = array_values(array_intersect($abilities, $entries->keys()->all()));

        while ($queue !== []) {
            $name = array_pop($queue);

            if (isset($chosen[$name]) || ! $entries->has($name)) {
                continue;
            }

            $chosen[$name] = true;
            array_push($queue, ...($entries[$name]['depends_on'] ?? []));
        }

        return $entries->keys()->filter(fn (string $name) => isset($chosen[$name]))->values()->all();
    }

    /**
     * @return list<string>
     */
    private function catalogueNames(): array
    {
        return array_column($this->catalog->all(), 'ability');
    }

    /**
     * A key made from the title, unused by any preset, never `owner`, and
     * random when the title has no letters a slug can keep.
     */
    private function freshKey(string $title): string
    {
        $base = Str::slug($title) ?: 'custom-'.Str::lower(Str::random(8));

        if ($base === RolePreset::OWNER) {
            $base = 'owner-preset';
        }

        $key = $base;
        $suffix = 2;

        while (RolePreset::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }
}
