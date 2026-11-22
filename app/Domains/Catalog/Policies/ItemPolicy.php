<?php

namespace App\Domains\Catalog\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may work with catalogue items.
 *
 * Every decision pairs a Bouncer ability with -- for anything aimed at an
 * existing row -- membership of that row's company, so an ability granted
 * inside one company never reaches another company's catalogue.
 *
 * Bouncer answers for the user it currently has scoped, not for the $user
 * handed in; that argument feeds only the membership half.
 */
class ItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-item', Item::class);
    }

    public function view(User $user, Item $item): bool
    {
        return BouncerFacade::can('view-item', $item) && $this->sameCompany($user, $item);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-item', Item::class);
    }

    public function update(User $user, Item $item): bool
    {
        return BouncerFacade::can('edit-item', $item) && $this->sameCompany($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->mayRemove($user, $item);
    }

    /**
     * Items are not soft-deleted, so neither restoring nor erasing is
     * reachable in practice; both answer with the delete rule.
     */
    public function restore(User $user, Item $item): bool
    {
        return $this->mayRemove($user, $item);
    }

    public function forceDelete(User $user, Item $item): bool
    {
        return $this->mayRemove($user, $item);
    }

    /**
     * The model-level counterpart for removing several items at once. It is
     * company-blind by construction -- there is no row here to check
     * membership against -- and the bulk endpoint does not consult it today,
     * gating on the bare "delete multiple items" permission instead.
     */
    public function deleteMultiple(User $user)
    {
        return BouncerFacade::can('delete-item', Item::class);
    }

    private function mayRemove(User $user, Item $item): bool
    {
        return BouncerFacade::can('delete-item', $item) && $this->sameCompany($user, $item);
    }

    private function sameCompany(User $user, Item $item): bool
    {
        return $user->isActingIn($item->company_id);
    }
}
