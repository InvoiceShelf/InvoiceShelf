<?php

namespace App\Domains\Receivables\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with payment methods.
 *
 * Two things about this table are worth stating plainly, because neither
 * follows the shape the other policies in the domain use.
 *
 * First, there is no ability of its own. Reading the list is checked against
 * the ability to *view* payments, adding a method against *create* or *edit*,
 * and renaming or removing one against *edit*, so a role that can only see
 * payments cannot reshape the company's set of payment methods.
 *
 * Second, the payment method is not what Bouncer is asked about: the check
 * names the payment class instead, so the ability is only ever evaluated at
 * class level even when a specific row is in hand. What ties a decision to a
 * row is the second half -- membership of the company the method belongs to --
 * which every row-aimed method applies. Creating is the exception, having no
 * row to belong anywhere; a member of any company may create.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class PaymentMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->mayReadPayments();
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->mayReadPayments() && $this->sameCompany($user, $paymentMethod);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-payment', Payment::class) || $this->mayEditPayments();
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->mayEditPayments() && $this->sameCompany($user, $paymentMethod);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->mayEditPayments() && $this->sameCompany($user, $paymentMethod);
    }

    /**
     * Restoring and erasing are governed the same way; payment methods are not
     * soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->mayEditPayments() && $this->sameCompany($user, $paymentMethod);
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->mayEditPayments() && $this->sameCompany($user, $paymentMethod);
    }

    /**
     * Reading the list follows the ability to see payments.
     */
    private function mayReadPayments(): bool
    {
        return BouncerFacade::can('view-payment', Payment::class);
    }

    /**
     * Changing it follows the ability to edit them.
     */
    private function mayEditPayments(): bool
    {
        return BouncerFacade::can('edit-payment', Payment::class);
    }

    private function sameCompany(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasCompany($paymentMethod->company_id);
    }
}
