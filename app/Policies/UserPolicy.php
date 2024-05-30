<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can invite the model.
     *
     * @return mixed
     */
    public function invite(User $user, User $model)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete models.
     *
     * @return mixed
     */
    public function deleteMultiple(User $user)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }
}
