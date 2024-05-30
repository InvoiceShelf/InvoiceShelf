<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ExpenseCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, ExpenseCategory $expenseCategory): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, ExpenseCategory $expenseCategory): bool
    {
        if (BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id)) {
            return true;
        }

        return false;
    }
}
