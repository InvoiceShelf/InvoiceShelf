<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\RecurringInvoice;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with recurring-invoice templates.
 *
 * Every decision has two halves: the Bouncer ability, and — for anything
 * aimed at an existing template — membership of the company that template
 * belongs to, so an ability held in one company never reaches another
 * company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 *
 * There is no sending here: a template is never mailed, only the invoices it
 * generates are.
 */
class RecurringInvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-recurring-invoice', RecurringInvoice::class);
    }

    public function view(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return BouncerFacade::can('view-recurring-invoice', $recurringInvoice)
            && $this->sameCompany($user, $recurringInvoice);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-recurring-invoice', RecurringInvoice::class);
    }

    public function update(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return BouncerFacade::can('edit-recurring-invoice', $recurringInvoice)
            && $this->sameCompany($user, $recurringInvoice);
    }

    public function delete(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->mayRemove($user, $recurringInvoice);
    }

    /**
     * Restoring and erasing answer to the delete ability as well; templates
     * are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->mayRemove($user, $recurringInvoice);
    }

    public function forceDelete(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->mayRemove($user, $recurringInvoice);
    }

    /**
     * The bulk-delete gate. It is handed no template, so only the ability half
     * applies and nothing here confines it to one company — the endpoint does
     * that itself when it resolves the ids.
     *
     * @return mixed
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-recurring-invoice', RecurringInvoice::class);
    }

    private function mayRemove(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return BouncerFacade::can('delete-recurring-invoice', $recurringInvoice)
            && $this->sameCompany($user, $recurringInvoice);
    }

    private function sameCompany(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->isActingIn($recurringInvoice->company_id);
    }
}
