<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The personal access tokens one account holds.
 *
 * Sanctum tokens never expire here, so a phone that walks off stays signed in
 * until somebody takes its token away. Everything in this class is therefore
 * scoped to one user: a caller can only ever see and revoke their own.
 */
class AccessTokenService
{
    /**
     * Every live token for this account, newest first.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function listFor(User $user): Collection
    {
        return $user->tokens()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Revoke one of this account's tokens.
     *
     * Scoped through the relation rather than looked up by id and then
     * checked, so somebody else's token id is indistinguishable from one that
     * never existed.
     *
     * @throws ModelNotFoundException
     */
    public function revokeFor(User $user, int|string $token_id): void
    {
        $user->tokens()->whereKey($token_id)->firstOrFail()->delete();
    }
}
