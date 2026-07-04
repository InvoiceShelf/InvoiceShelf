<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Authorization for credit notes (Stornorechnungen).
 *
 * A credit note is persisted as an invoice row (type = CREDIT_NOTE), so the
 * abilities map to the invoice permissions but are gated on the acting user
 * belonging to the source invoice's company (tenant isolation). Applied
 * consistently on every credit-note endpoint (issue #9 from PR #536).
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

    /**
     * Whether the user may view the given credit note.
     */
    public function view(User $user, Invoice $creditNote): bool
    {
        return BouncerFacade::can('view-invoice', $creditNote)
            && $user->hasCompany($creditNote->company_id);
    }

    /**
     * Whether the user may email the given credit note.
     */
    public function send(User $user, Invoice $creditNote): bool
    {
        return BouncerFacade::can('send-invoice', $creditNote)
            && $user->hasCompany($creditNote->company_id);
    }
}
