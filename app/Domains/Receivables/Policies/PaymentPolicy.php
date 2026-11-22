<?php

namespace App\Domains\Receivables\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with payments.
 *
 * Every decision has two halves: the Bouncer ability, and -- for anything
 * aimed at an existing row -- membership of the company that row belongs to,
 * so an ability held in one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 *
 * Four distinct abilities govern the payment: viewing, creating, editing and
 * deleting, plus a fifth for mailing the receipt out. Restoring and erasing
 * ride on the delete ability. The bulk delete is checked against the class
 * rather than a row, since it is granted before the ids are known.
 */
class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-payment', Payment::class);
    }

    public function view(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('view-payment', $payment) && $this->sameCompany($user, $payment);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-payment', Payment::class);
    }

    public function update(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('edit-payment', $payment) && $this->sameCompany($user, $payment);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->mayRemove($user, $payment);
    }

    /**
     * Restoring and erasing are governed by the delete ability as well;
     * payments are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return $this->mayRemove($user, $payment);
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return $this->mayRemove($user, $payment);
    }

    /**
     * Mailing the receipt to the customer.
     */
    public function send(User $user, Payment $payment)
    {
        return BouncerFacade::can('send-payment', $payment) && $this->sameCompany($user, $payment);
    }

    /**
     * Deleting a batch of payments in one request.
     *
     * Only the ability is asked for here: the rows arrive as a list of ids in
     * the request body, so there is nothing yet to check company membership
     * against.
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-payment', Payment::class);
    }

    private function mayRemove(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('delete-payment', $payment) && $this->sameCompany($user, $payment);
    }

    private function sameCompany(User $user, Payment $payment): bool
    {
        return $user->isActingIn($payment->company_id);
    }
}
