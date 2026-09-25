<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with expense headings.
 *
 * Headings have no abilities of their own: reading them asks for the *view*
 * ability on expenses, adding one for *create* or *edit* (the expense form
 * adds headings inline), renaming and removing them for *edit*, so a role
 * that can only read a company's expenses cannot reshape its headings. Anything aimed at an existing heading additionally requires
 * membership of the company that heading belongs to, so an ability held in
 * one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class ExpenseCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->mayRead();
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayReach($user, $expenseCategory);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-expense', Expense::class) || $this->mayEdit();
    }

    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayEdit() && $this->belongs($user, $expenseCategory);
    }

    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayEdit() && $this->belongs($user, $expenseCategory);
    }

    /**
     * Restoring and erasing sit on the same test as deleting; headings are not
     * soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayEdit() && $this->belongs($user, $expenseCategory);
    }

    public function forceDelete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayEdit() && $this->belongs($user, $expenseCategory);
    }

    /**
     * Both abilities are asked of the expense class rather than of any heading.
     */
    private function mayRead(): bool
    {
        return BouncerFacade::can('view-expense', Expense::class);
    }

    private function mayEdit(): bool
    {
        return BouncerFacade::can('edit-expense', Expense::class);
    }

    private function mayReach(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->mayRead() && $this->belongs($user, $expenseCategory);
    }

    private function belongs(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->hasCompany($expenseCategory->company_id);
    }
}
