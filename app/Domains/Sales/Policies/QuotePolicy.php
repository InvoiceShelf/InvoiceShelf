<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Quote;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with quotes.
 *
 * Every decision has two halves: the Bouncer ability, and — for anything
 * aimed at an existing offer — membership of the company that offer belongs
 * to, so an ability held in one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 *
 * Unlike an invoice, a quote carries no editing window: nothing is
 * allocated against it, so the ability and the membership are the whole test.
 */
class QuotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-quote', Quote::class);
    }

    public function view(User $user, Quote $quote): bool
    {
        return BouncerFacade::can('view-quote', $quote) && $this->sameCompany($user, $quote);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-quote', Quote::class);
    }

    public function update(User $user, Quote $quote): bool
    {
        return BouncerFacade::can('edit-quote', $quote) && $this->sameCompany($user, $quote);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->mayRemove($user, $quote);
    }

    /**
     * Restoring and erasing answer to the delete ability as well; quotes
     * are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, Quote $quote): bool
    {
        return $this->mayRemove($user, $quote);
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $this->mayRemove($user, $quote);
    }

    /**
     * Mailing the offer to its customer. Left without a return type, as it has
     * always been.
     *
     * @return mixed
     */
    public function send(User $user, Quote $quote)
    {
        return BouncerFacade::can('send-quote', $quote) && $this->sameCompany($user, $quote);
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
        return BouncerFacade::can('delete-quote', Quote::class);
    }

    private function mayRemove(User $user, Quote $quote): bool
    {
        return BouncerFacade::can('delete-quote', $quote) && $this->sameCompany($user, $quote);
    }

    private function sameCompany(User $user, Quote $quote): bool
    {
        return $user->hasCompany($quote->company_id) && (! request()->hasHeader('company') || (string) request()->header('company') === (string) $quote->company_id);
    }
}
