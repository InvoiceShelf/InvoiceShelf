<?php

namespace App\Http\Controllers\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\OAuth2Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as OAuth2Account;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * Sign-in with SSO over OAuth 2.0 / OpenID Connect: the authorization code
 * flow, with PKCE and Socialite's state check, against the identity provider
 * set up in config/services.php.
 *
 * Only existing users can sign in this way; it never creates one. The first
 * time an account at the identity provider signs in, it is linked to the one
 * user with the same email address. From then on only that link counts (the
 * account's "sub" claim, kept in users.oauth2_id): changing either email
 * address later does not break it, and no other account can take the user
 * over. `php artisan oauth2:unlink` removes a link.
 */
class OAuth2Controller extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(OAuth2Login::enabled(), 404);

        return $this->identityProvider()->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(OAuth2Login::enabled(), 404);

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->backToLogin('failed');
        }

        try {
            $account = $this->identityProvider()->user();
        } catch (InvalidStateException) {
            return $this->backToLogin('failed');
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToLogin('failed');
        }

        $user = $this->userFor($account);

        if (! $user) {
            return $this->backToLogin('no_account');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(AppServiceProvider::HOME);
    }

    /**
     * The user an account at the identity provider signs in as: the user
     * linked to it, or on its first sign-in the one user with its email
     * address, which this then links to it. A user linked to another account
     * is never taken over.
     */
    private function userFor(OAuth2Account $account): ?User
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
     * Socialite's "oauth2" driver (App\Support\OAuth2Provider), asking for
     * the scopes in OAUTH2_SCOPES, with PKCE.
     */
    private function identityProvider(): Provider
    {
        return Socialite::driver('oauth2')
            ->setScopes(OAuth2Login::scopes())
            ->enablePKCE();
    }

    /**
     * Back to the login page, which says why the sign-in failed: "failed" or
     * "no_account".
     */
    private function backToLogin(string $error): RedirectResponse
    {
        return redirect()->route('login')->with(OAuth2Login::ERROR, $error);
    }
}
