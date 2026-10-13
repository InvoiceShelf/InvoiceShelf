<?php

namespace App\Domains\Accounts\Policies;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may bring companies into being, hand them over, or wind them up.
 *
 * Two different questions are being asked here, and they do not agree with
 * each other. Creation looks at the company the request header points at:
 * whoever owns *that* company may open another one, which means an ordinary
 * member who owns nothing anywhere is refused. Transfer and deletion instead
 * look at the company being acted on and want the actor to be its recorded
 * owner, header or no header.
 *
 * The platform administrator may create a company from Administration mode,
 * where there is no active company header. Transfer and deletion still require
 * ownership of the named company.
 */
class CompanyPolicy
{
    use HandlesAuthorization;

    /**
     * Opening a new company.
     *
     * Judged against the active company, not the one about to exist.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOwner();
    }

    /**
     * Winding a company up.
     */
    public function delete(User $user, Company $company): bool
    {
        return $this->ownsOutright($user, $company);
    }

    /**
     * Handing a company to somebody else.
     *
     * Declared without a return type, as found.
     */
    public function transferOwnership(User $user, Company $company)
    {
        return $this->ownsOutright($user, $company);
    }

    /**
     * The actor is the company's recorded owner.
     *
     * Compared loosely, so an owner column holding a numeric string still
     * matches the id it names.
     */
    private function ownsOutright(User $user, Company $company): bool
    {
        return $user->id == $company->owner_id;
    }
}
