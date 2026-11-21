<?php

namespace App\Domains\Money\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\ExchangeRateProvider;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Abilities are resolved by Bouncer against the scoped role set; instance
 * checks additionally require the actor to be a member of the owning company.
 */
class ExchangeRateProviderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-exchange-rate-provider', ExchangeRateProvider::class);
    }

    public function view(User $user, ExchangeRateProvider $exchangeRateProvider): bool
    {
        return BouncerFacade::can('view-exchange-rate-provider', $exchangeRateProvider)
            && $user->isActingIn($exchangeRateProvider->company_id);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-exchange-rate-provider', ExchangeRateProvider::class);
    }

    public function update(User $user, ExchangeRateProvider $exchangeRateProvider): bool
    {
        return BouncerFacade::can('edit-exchange-rate-provider', $exchangeRateProvider)
            && $user->isActingIn($exchangeRateProvider->company_id);
    }

    public function delete(User $user, ExchangeRateProvider $exchangeRateProvider): bool
    {
        return BouncerFacade::can('delete-exchange-rate-provider', $exchangeRateProvider)
            && $user->isActingIn($exchangeRateProvider->company_id);
    }

    // Soft deletes are not wired up for providers; these remain declared but
    // intentionally answer nothing, as no route reaches them.
    public function restore(User $user, ExchangeRateProvider $exchangeRateProvider): bool
    {
        //
    }

    public function forceDelete(User $user, ExchangeRateProvider $exchangeRateProvider): bool
    {
        //
    }
}
