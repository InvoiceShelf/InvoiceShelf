<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\Exceptions\SsoSignInRefused;
use App\Domains\Accounts\Application\OidcLogin;
use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Domains\Accounts\Application\SsoHandoffs;
use App\Domains\Accounts\Application\SsoLogin;
use App\Platform\Http\Controller;
use App\Support\SpaStylesheets;
use App\Support\SpaTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Where a single sign-on starts: the login page's button, and the browser
 * the mobile app opens for a hand-off (SsoHandoffs). Both go on to the
 * identity provider with the configured protocol, OpenID Connect today, and
 * come back to its callback (OidcController).
 */
class SsoController extends Controller
{
    /**
     * Characters of the device name the confirmation shows.
     */
    private const DEVICE_NAME_LENGTH = 60;

    public function __construct(private readonly SsoHandoffs $handoffs) {}

    /**
     * Send a browser to the identity provider, remembering the page the login
     * page was asked to return to.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(SsoLogin::enabled(), 404);

        $next = PostLoginRedirect::sanitize($request->query('next'));
        $session = $request->session();
        $session->forget(SsoLogin::HANDOFF);
        $session->put(SsoLogin::NEXT, $next);

        try {
            return OidcLogin::provider()->redirect();
        } catch (SsoSignInRefused $refusal) {
            return redirect()->to(SsoLogin::loginUrl($next))->with(SsoLogin::ERROR, $refusal->logged());
        }
    }

    /**
     * The mobile app's way in: the system browser it opened lands here, for
     * the hand-off the app began, and is asked whether to go on.
     *
     * Anyone can start a hand-off and send its address to somebody else. If
     * that person's browser went straight to the identity provider, a
     * sign-in they are still holding there would complete without a word,
     * and the code would go to the sender's device. So nothing happens until
     * the person in this browser confirms, with a form only this page can
     * post (CSRF), and the identity provider is asked to sign them in again.
     */
    public function app(string $handoff): Response
    {
        abort_unless(SsoLogin::enabled(), 404);

        $deviceName = $this->handoffs->deviceName($handoff);

        abort_if($deviceName === null, 404);

        $locale = app()->getLocale();

        return response()
            ->view('auth.sso-app', [
                'handoff' => $handoff,
                'deviceName' => Str::limit($deviceName, self::DEVICE_NAME_LENGTH),
                'stylesheets' => SpaStylesheets::urls(),
                'theme' => get_app_setting('admin_portal_theme') ?? 'invoiceshelf',
                'locale' => $locale,
                't' => fn (string $key, array $replace = []): string => SpaTranslations::get($locale, $key, $replace),
            ])
            ->header('X-Frame-Options', 'DENY')
            ->header('Content-Security-Policy', "frame-ancestors 'none'")
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The person in the browser confirmed: go on to the identity provider for
     * the hand-off. A web session this browser may hold plays no part.
     */
    public function continueApp(Request $request, string $handoff): RedirectResponse
    {
        abort_unless(SsoLogin::enabled() && $this->handoffs->pending($handoff), 404);

        $session = $request->session();
        $session->forget(SsoLogin::NEXT);
        $session->put(SsoLogin::HANDOFF, $handoff);

        try {
            return OidcLogin::provider(['prompt' => 'login'])->redirect();
        } catch (SsoSignInRefused $refusal) {
            return redirect()->away($this->handoffs->appLink($handoff, ['error' => $refusal->logged()]));
        }
    }

    /**
     * The person in the browser did not start this sign-in: end the hand-off,
     * and tell the app.
     */
    public function cancelApp(string $handoff): RedirectResponse
    {
        abort_unless(SsoLogin::enabled() && $this->handoffs->pending($handoff), 404);

        $this->handoffs->cancel($handoff);

        return redirect()->away($this->handoffs->appLink($handoff, ['error' => 'cancelled']));
    }
}
