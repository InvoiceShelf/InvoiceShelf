<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as OAuth2Account;

/**
 * Which user an account at the identity provider signs in as.
 *
 * Only existing users can sign in with SSO; it never creates one. The first
 * time an account signs in, it is linked to the one user with the same email
 * address. From then on only that link counts (the account's "sub" claim,
 * kept in users.oauth2_id): changing either email address later does not
 * break it, and no other account can take the user over. `oauth2:unlink`
 * removes a link.
 */
final class OAuth2LoginService
{
    /**
     * The user the account signs in as: the user linked to it, or on its
     * first sign-in the one user with its email address, which this then
     * links to it. Null when it may not sign in; the log says why.
     */
    public function userFor(OAuth2Account $account): ?User
    {
        $subject = (string) $account->getId();

        if ($subject === '') {
            return null;
        }

        $linked = User::query()->where('oauth2_id', $subject)->first();

        if ($linked) {
            return $linked;
        }

        $email = Str::lower(trim((string) $account->getEmail()));
        $users = $email === ''
            ? collect()
            : User::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();

        if ($users->count() !== 1) {
            Log::notice('Sign-in with SSO refused: no single user has this email address.', [
                'email' => $email,
                'oauth2_id' => $subject,
            ]);

            return null;
        }

        $user = $users->first();

        if ($user->oauth2_id !== null) {
            Log::notice('Sign-in with SSO refused: the user with this email address is linked to another account. `php artisan oauth2:unlink` removes that link.', [
                'user_id' => $user->id,
                'email' => $email,
                'oauth2_id' => $subject,
            ]);

            return null;
        }

        $user->forceFill(['oauth2_id' => $subject])->save();

        Log::info('Linked a user to their account at the identity provider on their first sign-in with SSO.', [
            'user_id' => $user->id,
            'oauth2_id' => $subject,
        ]);

        return $user;
    }

    /**
     * The user with this email address, in any mix of upper and lower case.
     */
    public function userWithEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first();
    }

    /**
     * Removes the user's link, so their next sign-in with SSO links them
     * again by email address. False when they had none.
     */
    public function unlink(User $user): bool
    {
        if ($user->oauth2_id === null) {
            return false;
        }

        $user->forceFill(['oauth2_id' => null])->save();

        return true;
    }
}
