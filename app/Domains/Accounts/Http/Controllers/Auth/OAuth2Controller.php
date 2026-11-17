<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\OAuth2Handoffs;
use App\Domains\Accounts\Application\OAuth2Login;
use App\Domains\Accounts\Application\OAuth2LoginService;
use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * Sign-in with SSO over OAuth 2.0 / OpenID Connect: the authorization code
 * flow, with PKCE and Socialite's state check, against the identity provider
 * configured in config/services.php.
 *
 * A browser that started at `redirect` ends signed in to the `web` session and
 * goes on to its `next` page through PostLoginRedirect. A browser the mobile
 * app opened at `app` ends at the app's link instead, carrying a one-time code
 * for the app's hand-off (OAuth2Handoffs). OAuth2LoginService decides which
 * user an account signs in as.
 */
class OAuth2Controller extends Controller
{
    public function __construct(
        private readonly OAuth2LoginService $accounts,
        private readonly OAuth2Handoffs $handoffs,
    ) {}

    /**
     * Send a browser to the identity provider, remembering the page the login
     * page was asked to return to.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(OAuth2Login::enabled(), 404);

        $session = $request->session();
        $session->forget(OAuth2Login::HANDOFF);
        $session->put(OAuth2Login::NEXT, PostLoginRedirect::sanitize($request->query('next')));

        return $this->identityProvider()->redirect();
    }

    /**
     * The mobile app's way in: the system browser it opened starts here, for
     * the hand-off the app began. A web session this browser may hold plays
     * no part.
     */
    public function app(Request $request, string $handoff): RedirectResponse
    {
        abort_unless(OAuth2Login::enabled() && $this->handoffs->pending($handoff), 404);

        $session = $request->session();
        $session->forget(OAuth2Login::NEXT);
        $session->put(OAuth2Login::HANDOFF, $handoff);

        return $this->identityProvider()->redirect();
    }

    /**
     * Where the identity provider sends the browser back to.
     *
     * Not behind the `guest` middleware: the browser of a mobile hand-off may
     * be signed in to the web app already, and that must not end its
     * sign-in. A signed-in browser of the web flow goes straight on instead.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(OAuth2Login::enabled(), 404);

        $session = $request->session();
        $handoff = $session->pull(OAuth2Login::HANDOFF);
        $next = PostLoginRedirect::sanitize($session->pull(OAuth2Login::NEXT)) ?? AppServiceProvider::HOME;

        if ($handoff === null && Auth::guard('web')->check()) {
            return redirect()->to($next);
        }

        $user = $this->signedInUser($request);

        if ($handoff !== null) {
            $code = $user instanceof User ? $this->handoffs->complete($handoff, $user) : null;

            return $this->backToApp($handoff, $code !== null
                ? ['code' => $code]
                : ['error' => $user instanceof User ? 'failed' : $user]);
        }

        if (! $user instanceof User) {
            return redirect()->route('login')->with(OAuth2Login::ERROR, $user);
        }

        Auth::guard('web')->login($user);
        $session->regenerate();

        return redirect()->to($next);
    }

    /**
     * The user the identity provider's answer signs in, or why there is none:
     * "failed" or "no_account".
     */
    private function signedInUser(Request $request): User|string
    {
        if ($request->filled('error') || ! $request->filled('code')) {
            return 'failed';
        }

        try {
            $account = $this->identityProvider()->user();
        } catch (InvalidStateException) {
            return 'failed';
        } catch (Throwable $exception) {
            report($exception);

            return 'failed';
        }

        return $this->accounts->userFor($account) ?? 'no_account';
    }

    /**
     * Socialite's "oauth2" driver (OAuth2Provider), asking for the scopes in
     * OAUTH2_SCOPES, with PKCE.
     */
    private function identityProvider(): Provider
    {
        return Socialite::driver('oauth2')
            ->setScopes(OAuth2Login::scopes())
            ->enablePKCE();
    }

    /**
     * Send the browser back to the mobile app with the outcome of its
     * hand-off: a one-time `code`, or an `error` ("failed" or "no_account").
     *
     * @param  array<string, string>  $outcome
     */
    private function backToApp(string $handoff, array $outcome): RedirectResponse
    {
        $query = http_build_query(['handoff' => $handoff, ...$outcome]);

        return redirect()->away(config('invoiceshelf.client.app_link').'?'.$query);
    }
}
