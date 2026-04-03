<?php

namespace App\Services;

use App\Http\Requests\UserRequest;
use App\Models\CompanySetting;
use App\Models\User;
use Silber\Bouncer\BouncerFacade;

class UserService
{
    public function create(UserRequest $request): User
    {
        $user = User::create($request->getUserPayload());

        $user->setSettings([
            'language' => CompanySetting::getSetting('language', $request->header('company')),
        ]);

        $companies = collect($request->companies);
        $user->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($user)->roles([$company['role']]);
        }

        return $user;
    }

    public function update(User $user, UserRequest $request): User
    {
        $user->update($request->getUserPayload());

        $companies = collect($request->companies);
        $user->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($user)->roles([$company['role']]);
        }

        return $user;
    }

    public function delete(array $ids): bool
    {
        foreach ($ids as $id) {
            $user = User::find($id);

            if ($user->invoices()->exists()) {
                $user->invoices()->update(['creator_id' => null]);
            }

            if ($user->estimates()->exists()) {
                $user->estimates()->update(['creator_id' => null]);
            }

            if ($user->customers()->exists()) {
                $user->customers()->update(['creator_id' => null]);
            }

            if ($user->recurringInvoices()->exists()) {
                $user->recurringInvoices()->update(['creator_id' => null]);
            }

            if ($user->expenses()->exists()) {
                $user->expenses()->update(['creator_id' => null]);
            }

            if ($user->payments()->exists()) {
                $user->payments()->update(['creator_id' => null]);
            }

            if ($user->items()->exists()) {
                $user->items()->update(['creator_id' => null]);
            }

            if ($user->settings()->exists()) {
                $user->settings()->delete();
            }

            $user->delete();
        }

        return true;
    }
}
