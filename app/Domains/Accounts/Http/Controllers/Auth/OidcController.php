<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\Exceptions\SsoSignInRefused;
use App\Domains\Accounts\Application\OidcLogin;
use App\Domains\Accounts\Application\OidcLoginService;
use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Domains\Accounts\Application\SsoHandoffs;
use App\Domains\Accounts\Application\SsoLogin;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * The end of a single sign-on over OpenID Connect: the authorization code
 * flow, with PKCE and Socialite's state check, comes back here from the
 * identity provider configured in config/services.php (OidcLogin). It
 * started at SsoController.
 *
 * A browser sign-in ends signed in to the `web` session and goes on to its
 * `next` page through PostLoginRedirect. A mobile app's hand-off ends at the
 * app's link instead, carrying a one-time code (SsoHandoffs).
 * OidcLoginService decides which user an account signs in as.
 */
class OidcController extends Controller
{
    public function __construct(
        private readonly OidcLoginService $accounts,
        private readonly SsoHandoffs $handoffs,
    ) {}

    /**
     * Where the identity provider sends the browser back to.
     *
     * Not behind the `guest` middleware: the browser of a mobile hand-off may
     * be signed in to the web app already, and that must not end its
     * sign-in. A signed-in browser of the web flow goes straight on instead.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(OidcLogin::enabled(), 404);

        $session = $request->session();
        $handoff = $session->pull(SsoLogin::HANDOFF);
        $next = PostLoginRedirect::sanitize($session->pull(SsoLogin::NEXT));

        if ($handoff === null && Auth::guard('web')->check()) {
            return redirect()->to($next ?? AppServiceProvider::HOME);
        }

        $user = $this->signedInUser($request);

        if ($handoff !== null) {
            $code = $user instanceof User ? $this->handoffs->complete($handoff, $user) : null;

            return redirect()->away($this->handoffs->appLink($handoff, $code !== null
                ? ['code' => $code]
                : ['error' => $user instanceof User ? SsoLogin::FAILED : $user]));
        }

        if (! $user instanceof User) {
            return redirect()->to(SsoLogin::loginUrl($next))->with(SsoLogin::ERROR, $user);
        }

        Auth::guard('web')->login($user);
        $session->regenerate();

        return redirect()->to($next ?? AppServiceProvider::HOME);
    }

    /**
     * The user the identity provider's answer signs in, or why there is none:
     * one of the SsoLogin error codes.
     */
    private function signedInUser(Request $request): User|string
    {
        if ($request->filled('error')) {
            Log::notice('Sign-in with SSO failed: the identity provider answered with an error.', [
                'error' => (string) $request->query('error'),
                'error_description' => (string) $request->query('error_description'),
            ]);

            return SsoLogin::FAILED;
        }

        if (! $request->filled('code')) {
            return SsoLogin::FAILED;
        }

        try {
            return $this->accounts->userFor(OidcLogin::provider()->user());
        } catch (InvalidStateException) {
            return SsoLogin::FAILED;
        } catch (SsoSignInRefused $refusal) {
            return $refusal->logged();
        } catch (Throwable $exception) {
            report($exception);

            return SsoLogin::FAILED;
        }
    }
}
