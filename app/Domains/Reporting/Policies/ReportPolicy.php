<?php

namespace App\Domains\Reporting\Policies;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may pull the financial reports.
 */
class ReportPolicy
{
    use HandlesAuthorization;

    /**
     * Membership is checked alongside the ability. The report URLs address a
     * company by its hash, and that hash is an address rather than a
     * credential: holding it opens nothing on its own.
     *
     * The return type stays undeclared: this is the signature the gate has
     * always exposed, and reflection over it is part of the contract.
     */
    public function viewReport(User $user, Company $company)
    {
        return BouncerFacade::can('view-financial-reports')
            && $user->isActingIn($company->id);
    }
}
