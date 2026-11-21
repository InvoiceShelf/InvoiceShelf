<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Expense;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with expenses.
 *
 * Every decision has two halves: the Bouncer ability, and -- for anything
 * aimed at an existing row -- membership of the company that row belongs to,
 * so an ability held in one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class ExpensePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-expense', Expense::class);
    }

    public function view(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('view-expense', $expense) && $this->sameCompany($user, $expense);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-expense', Expense::class);
    }

    public function update(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('edit-expense', $expense) && $this->sameCompany($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->mayRemove($user, $expense);
    }

    /**
     * Restoring and erasing are governed by the delete ability as well;
     * expenses are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $this->mayRemove($user, $expense);
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return $this->mayRemove($user, $expense);
    }

    /**
     * Clearing a batch of expenses at once.
     *
     * There is no row to check membership against here, so the delete ability
     * on the class is the whole test; the deletion itself is scoped to the
     * acting company by the caller.
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-expense', Expense::class);
    }

    private function mayRemove(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('delete-expense', $expense) && $this->sameCompany($user, $expense);
    }

    private function sameCompany(User $user, Expense $expense): bool
    {
        return $user->isActingIn($expense->company_id);
    }
}
