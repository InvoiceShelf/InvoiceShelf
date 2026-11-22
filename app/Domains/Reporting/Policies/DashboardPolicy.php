<?php

namespace App\Domains\Reporting\Policies;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may open the company overview.
 */
class DashboardPolicy
{
    use HandlesAuthorization;

    /**
     * The ability alone is not enough: the account has to belong to the
     * company it is asking about, so a granted role in one tenancy cannot be
     * turned on another.
     */
    public function view(User $user, Company $company): bool
    {
        return BouncerFacade::can('dashboard')
            && $user->isActingIn($company->id);
    }
}
