<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Company $company): bool
    {
        if ($user->id == $company->owner_id) {
            return true;
        }

        return false;
    }

    public function transferOwnership(User $user, Company $company)
    {
        if ($user->id == $company->owner_id) {
            return true;
        }

        return false;
    }
}
