<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Silber\Bouncer\BouncerFacade;

abstract class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-'.static::ABILITY, static::MODEL);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-'.static::ABILITY, static::MODEL);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'view');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'edit');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'delete');
    }

    private function allows(User $user, Model $record, string $verb): bool
    {
        return (int) request()->header('company') === (int) $record->company_id
            && $user->hasCompany($record->company_id)
            && BouncerFacade::can($verb.'-'.static::ABILITY, $record);
    }
}
