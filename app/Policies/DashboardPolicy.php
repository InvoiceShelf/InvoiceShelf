<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class DashboardPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Company $company): bool
    {
        if (BouncerFacade::can('dashboard') && $user->hasCompany($company->id)) {
            return true;
        }

        return false;
    }
}
