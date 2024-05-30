<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Company;
use App\Models\User;

class SettingsPolicy
{
    use HandlesAuthorization;

    public function manageCompany(User $user, Company $company)
    {
        if ($user->id == $company->owner_id) {
            return true;
        }

        return false;
    }

    public function manageBackups(User $user)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    public function manageFileDisk(User $user)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    public function manageEmailConfig(User $user)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }

    public function manageSettings(User $user)
    {
        if ($user->isOwner()) {
            return true;
        }

        return false;
    }
}
