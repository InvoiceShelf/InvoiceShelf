<?php

namespace App\Domains\Metadata\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with custom-field definitions.
 *
 * Every decision aimed at a definition has two halves: the Bouncer ability,
 * and membership of the company that definition belongs to, so an ability
 * held in one company never reaches another company's fields. The two
 * class-level decisions -- listing and creating -- have no row to check
 * membership against and rest on the ability alone.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument only feeds the membership half.
 */
class CustomFieldPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-custom-field', CustomField::class);
    }

    public function view(User $user, CustomField $customField): bool
    {
        return BouncerFacade::can('view-custom-field', $customField) && $this->sameCompany($user, $customField);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-custom-field', CustomField::class);
    }

    public function update(User $user, CustomField $customField): bool
    {
        return BouncerFacade::can('edit-custom-field', $customField) && $this->sameCompany($user, $customField);
    }

    public function delete(User $user, CustomField $customField): bool
    {
        return $this->mayRemove($user, $customField);
    }

    /**
     * Restoring and erasing are governed by the delete ability as well;
     * definitions are not soft-deleted, so neither is reachable in practice.
     */
    public function restore(User $user, CustomField $customField): bool
    {
        return $this->mayRemove($user, $customField);
    }

    public function forceDelete(User $user, CustomField $customField): bool
    {
        return $this->mayRemove($user, $customField);
    }

    private function mayRemove(User $user, CustomField $customField): bool
    {
        return BouncerFacade::can('delete-custom-field', $customField) && $this->sameCompany($user, $customField);
    }

    private function sameCompany(User $user, CustomField $customField): bool
    {
        return $user->isActingIn($customField->company_id);
    }
}
