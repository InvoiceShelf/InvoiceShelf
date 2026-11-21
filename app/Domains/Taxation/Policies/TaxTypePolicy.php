<?php

namespace App\Domains\Taxation\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with tax types.
 *
 * Every decision has two halves: the Bouncer ability, and — for anything
 * aimed at an existing row — membership of the company that row belongs to,
 * so an ability held in one company never reaches another company's data.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class TaxTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-tax-type', TaxType::class);
    }

    public function view(User $user, TaxType $taxType): bool
    {
        return BouncerFacade::can('view-tax-type', $taxType) && $this->sameCompany($user, $taxType);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-tax-type', TaxType::class);
    }

    public function update(User $user, TaxType $taxType): bool
    {
        return BouncerFacade::can('edit-tax-type', $taxType) && $this->sameCompany($user, $taxType);
    }

    public function delete(User $user, TaxType $taxType): bool
    {
        return $this->mayRemove($user, $taxType);
    }

    /**
     * Restoring and erasing are governed by the delete ability as well; tax
     * types are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, TaxType $taxType): bool
    {
        return $this->mayRemove($user, $taxType);
    }

    public function forceDelete(User $user, TaxType $taxType): bool
    {
        return $this->mayRemove($user, $taxType);
    }

    private function mayRemove(User $user, TaxType $taxType): bool
    {
        return BouncerFacade::can('delete-tax-type', $taxType) && $this->sameCompany($user, $taxType);
    }

    private function sameCompany(User $user, TaxType $taxType): bool
    {
        return $user->isActingIn($taxType->company_id);
    }
}
