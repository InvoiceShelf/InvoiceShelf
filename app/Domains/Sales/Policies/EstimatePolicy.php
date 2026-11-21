<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Estimate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with estimates.
 *
 * Every decision has two halves: the Bouncer ability, and — for anything
 * aimed at an existing offer — membership of the company that offer belongs
 * to, so an ability held in one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 *
 * Unlike an invoice, an estimate carries no editing window: nothing is
 * allocated against it, so the ability and the membership are the whole test.
 */
class EstimatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-estimate', Estimate::class);
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('view-estimate', $estimate) && $this->sameCompany($user, $estimate);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-estimate', Estimate::class);
    }

    public function update(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('edit-estimate', $estimate) && $this->sameCompany($user, $estimate);
    }

    public function delete(User $user, Estimate $estimate): bool
    {
        return $this->mayRemove($user, $estimate);
    }

    /**
     * Restoring and erasing answer to the delete ability as well; estimates
     * are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, Estimate $estimate): bool
    {
        return $this->mayRemove($user, $estimate);
    }

    public function forceDelete(User $user, Estimate $estimate): bool
    {
        return $this->mayRemove($user, $estimate);
    }

    /**
     * Mailing the offer to its customer. Left without a return type, as it has
     * always been.
     *
     * @return mixed
     */
    public function send(User $user, Estimate $estimate)
    {
        return BouncerFacade::can('send-estimate', $estimate) && $this->sameCompany($user, $estimate);
    }

    /**
     * The bulk-delete gate. It is handed no offer, so only the ability half
     * applies and nothing here confines it to one company — the endpoint does
     * that itself when it resolves the ids.
     *
     * @return mixed
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-estimate', Estimate::class);
    }

    private function mayRemove(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('delete-estimate', $estimate) && $this->sameCompany($user, $estimate);
    }

    private function sameCompany(User $user, Estimate $estimate): bool
    {
        return $user->isActingIn($estimate->company_id);
    }
}
