<?php

namespace App\Policies;

use App\Models\TaxType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class TaxTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-tax-type', TaxType::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, TaxType $taxType): bool
    {
        if (BouncerFacade::can('view-tax-type', $taxType) && $user->hasCompany($taxType->company_id)) {
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
        if (BouncerFacade::can('create-tax-type', TaxType::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, TaxType $taxType): bool
    {
        if (BouncerFacade::can('edit-tax-type', $taxType) && $user->hasCompany($taxType->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, TaxType $taxType): bool
    {
        if (BouncerFacade::can('delete-tax-type', $taxType) && $user->hasCompany($taxType->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, TaxType $taxType): bool
    {
        if (BouncerFacade::can('delete-tax-type', $taxType) && $user->hasCompany($taxType->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, TaxType $taxType): bool
    {
        if (BouncerFacade::can('delete-tax-type', $taxType) && $user->hasCompany($taxType->company_id)) {
            return true;
        }

        return false;
    }
}
