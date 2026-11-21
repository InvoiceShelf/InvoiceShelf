<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with invoices — credit notes included, since those are invoice
 * rows and ride on the same abilities.
 *
 * Every decision has two halves: the Bouncer ability, and, for anything
 * aimed at an existing document, acting in the company that document belongs
 * to (a member of it, with Bouncer scoped to it), so an ability held in one
 * company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-invoice', Invoice::class);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return BouncerFacade::can('view-invoice', $invoice) && $this->sameCompany($user, $invoice);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-invoice', Invoice::class);
    }

    /**
     * Editing answers to a third half on top of the usual two: the document
     * has to still be open to it.
     *
     * A credit note never is. It is a reversal, immutable once minted, because
     * saving it back through the invoice form would recompute its totals
     * positive. For everything else the model's own accessor decides, which is
     * where the company's retrospective-edits setting is read.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isCreditNote()
            && BouncerFacade::can('edit-invoice', $invoice)
            && $this->sameCompany($user, $invoice)
            && $invoice->allow_edit;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->mayRemove($user, $invoice);
    }

    /**
     * Restoring and erasing answer to the delete ability as well; invoices are
     * not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $this->mayRemove($user, $invoice);
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $this->mayRemove($user, $invoice);
    }

    /**
     * Mailing the document to its customer. Left without a return type, as it
     * has always been.
     *
     * @return mixed
     */
    public function send(User $user, Invoice $invoice)
    {
        return BouncerFacade::can('send-invoice', $invoice) && $this->sameCompany($user, $invoice);
    }

    /**
     * The bulk-delete gate. It is handed no document, so only the ability half
     * applies and nothing here confines it to one company — the endpoint does
     * that itself when it resolves the ids.
     *
     * @return mixed
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-invoice', Invoice::class);
    }

    private function mayRemove(User $user, Invoice $invoice): bool
    {
        return BouncerFacade::can('delete-invoice', $invoice) && $this->sameCompany($user, $invoice);
    }

    private function sameCompany(User $user, Invoice $invoice): bool
    {
        return $user->isActingIn($invoice->company_id);
    }
}
