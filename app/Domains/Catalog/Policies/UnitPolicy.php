<?php

namespace App\Domains\Catalog\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with measurement units.
 *
 * Units have no abilities of their own. Listing and reading them rest on the
 * item *view* ability. Adding one takes the item *create* or *edit* ability,
 * since the item form adds units inline; changing or removing one takes
 * *edit*. A role that can only see the catalogue cannot reshape the unit list
 * behind it.
 *
 * The ability is always asked about the Item class rather than the unit at
 * hand, which means Bouncer's own scoping never sees the unit; keeping
 * cross-company reach out is left entirely to the membership half, applied on
 * the actions that have a row to check.
 */
class UnitPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->mayViewItems();
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->mayViewItems() && $this->sameCompany($user, $unit);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-item', Item::class) || $this->mayEditItems();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->mayEditItems() && $this->sameCompany($user, $unit);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->mayEditItems() && $this->sameCompany($user, $unit);
    }

    /**
     * Units are not soft-deleted, so restoring and erasing are unreachable;
     * both mirror the delete rule.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $this->mayEditItems() && $this->sameCompany($user, $unit);
    }

    public function forceDelete(User $user, Unit $unit): bool
    {
        return $this->mayEditItems() && $this->sameCompany($user, $unit);
    }

    private function mayViewItems(): bool
    {
        return BouncerFacade::can('view-item', Item::class);
    }

    private function mayEditItems(): bool
    {
        return BouncerFacade::can('edit-item', Item::class);
    }

    private function sameCompany(User $user, Unit $unit): bool
    {
        return $user->hasCompany($unit->company_id);
    }
}
