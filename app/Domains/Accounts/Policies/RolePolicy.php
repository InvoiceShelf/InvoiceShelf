<?php

namespace App\Domains\Accounts\Policies;

use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\Database\Role;

/**
 * Who may work with per-company roles.
 *
 * Every entry asks whether the actor owns the company named in the `company`
 * header, and where a role is handed in, whether that role belongs to the
 * same company. Bouncer's query scoping does not cover route binding, so a
 * role of another company would otherwise resolve here.
 */
class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Browsing the roles of the active company.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Reading one role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Defining a role.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Renaming a role or resyncing its abilities.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Dropping a role. Whether anybody still holds it is settled downstream,
     * not here.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Bringing a role back — unreachable, as roles are not soft-deleted.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Erasing a role for good — unreachable for the same reason.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    private function inActiveCompany(Role $role): bool
    {
        return (int) $role->scope === (int) request()->header('company');
    }
}
