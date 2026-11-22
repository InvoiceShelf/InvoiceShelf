<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with contacts.
 *
 * Every answer has two halves: the Bouncer ability, and — whenever an existing
 * row is named — membership of the company that row belongs to, so an ability
 * granted inside one company never reaches another company's contacts.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * passed in; that argument feeds the membership half only.
 */
class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-customer', Customer::class);
    }

    public function view(User $user, Customer $customer): bool
    {
        return BouncerFacade::can('view-customer', $customer) && $this->sameCompany($user, $customer);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-customer', Customer::class);
    }

    public function update(User $user, Customer $customer): bool
    {
        return BouncerFacade::can('edit-customer', $customer) && $this->sameCompany($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->mayRemove($user, $customer);
    }

    /**
     * Contacts are not soft-deleted, so neither restoring nor erasing is
     * reachable; both defer to the delete ability regardless.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $this->mayRemove($user, $customer);
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $this->mayRemove($user, $customer);
    }

    /**
     * Batch removal. Class-level, so there is no company half to check.
     *
     * Unused in practice: the bulk endpoint authorises the bare "delete
     * multiple customers" ability string, which Bouncer settles before the
     * gate ever looks for a policy method. Left in place, missing return type
     * included.
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-customer', Customer::class);
    }

    private function mayRemove(User $user, Customer $customer): bool
    {
        return BouncerFacade::can('delete-customer', $customer) && $this->sameCompany($user, $customer);
    }

    private function sameCompany(User $user, Customer $customer): bool
    {
        return $user->isActingIn($customer->company_id);
    }
}
