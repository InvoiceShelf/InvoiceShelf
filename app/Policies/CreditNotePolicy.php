<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class CreditNotePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-credit-note', CreditNote::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, CreditNote $creditNote): bool
    {
        if (BouncerFacade::can('view-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
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
        if (BouncerFacade::can('create-credit-note', CreditNote::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, CreditNote $creditNote): bool
    {
        if (BouncerFacade::can('edit-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, CreditNote $creditNote): bool
    {
        if (BouncerFacade::can('delete-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, CreditNote $creditNote): bool
    {
        if (BouncerFacade::can('delete-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, CreditNote $creditNote): bool
    {
        if (BouncerFacade::can('delete-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can send email of the model.
     *
     * @return mixed
     */
    public function send(User $user, CreditNote $creditNote)
    {
        if (BouncerFacade::can('send-credit-note', $creditNote) && $user->hasCompany($creditNote->company_id)) {
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
        if (BouncerFacade::can('delete-credit-note', CreditNote::class)) {
            return true;
        }

        return false;
    }
}
