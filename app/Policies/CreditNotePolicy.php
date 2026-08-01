<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Authorization for credit notes (Stornorechnungen).
 *
 * A credit note is persisted as an invoice row (type = CREDIT_NOTE), so once it
 * exists the ordinary invoice abilities (view, send, delete) govern it through
 * InvoicePolicy. What needs its own rule is minting one: the ability maps to
 * creating an invoice but is gated on the acting user belonging to the *source*
 * invoice's company (tenant isolation, issue #9 from PR #536).
 */
class CreditNotePolicy
{
    use HandlesAuthorization;

    /**
     * Whether the user may create a credit note for the given source invoice.
     */
    public function create(User $user, Invoice $invoice): bool
    {
        return BouncerFacade::can('create-invoice', Invoice::class)
            && $user->hasCompany($invoice->company_id);
    }
}
