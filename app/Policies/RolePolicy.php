<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\Database\Role;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->isOwner() && $this->inActiveCompany($role);
    }

    /**
     * Bouncer's query scoping does not cover route binding, so a role of
     * another company would otherwise resolve here.
     */
    private function inActiveCompany(Role $role): bool
    {
        return (int) $role->scope === (int) request()->header('company');
    }
}
