<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class PaymentMethodPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class) && $user->hasCompany($paymentMethod->company_id)) {
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
        if (BouncerFacade::can('view-payment', Payment::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class) && $user->hasCompany($paymentMethod->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class) && $user->hasCompany($paymentMethod->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class) && $user->hasCompany($paymentMethod->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        if (BouncerFacade::can('view-payment', Payment::class) && $user->hasCompany($paymentMethod->company_id)) {
            return true;
        }

        return false;
    }
}
