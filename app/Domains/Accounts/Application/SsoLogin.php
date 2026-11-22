<?php

namespace App\Domains\Accounts\Application;

/**
 * Single sign-on as the login page, the mobile app and the user see it,
 * whatever protocol is behind it: whether it is offered, where the button
 * points, and why a sign-in that came back failed. OpenID Connect is the one
 * protocol today (OidcLogin has its settings).
 *
 * The browser gets this as `window.sso_login` from the Blade shell; the
 * mobile app sees `sso_login` in the client manifest and signs in through a
 * hand-off instead (SsoHandoffs).
 */
final class SsoLogin
{
    /**
     * Session key that carries why a sign-in failed back to the login page:
     * one of the error codes below.
     */
    public const ERROR = 'sso_login_error';

    /**
     * Session key for the page to open after the sign-in, from the login
     * page's `next` (see PostLoginRedirect).
     */
    public const NEXT = 'sso_login_next';

    /**
     * Session key for the mobile app's hand-off a browser sign-in belongs to.
     */
    public const HANDOFF = 'sso_login_handoff';

    /**
     * What the login page or the mobile app tells the user when a sign-in
     * ends without one: it did not work, the account matches no user here,
     * or the identity provider has not verified the email address the first
     * sign-in would link by.
     */
    public const FAILED = 'failed';

    public const NO_ACCOUNT = 'no_account';

    public const EMAIL_UNVERIFIED = 'email_unverified';

    /**
     * Whether single sign-on is set up. Configuration only: nothing here
     * asks the identity provider anything.
     */
    public static function enabled(): bool
    {
        return OidcLogin::enabled();
    }

    /**
     * What the login page needs for its "Sign in with SSO" button: where the
     * button points, and why the sign-in that just came back failed, if it
     * did.
     *
     * @return array{url: string, error: string|null}
     */
    public static function clientState(): array
    {
        return [
            'url' => route('sso.redirect', [], false),
            'error' => session(self::ERROR),
        ];
    }

    /**
     * The login page, still on the way to the page the sign-in was heading
     * to, if that was a safe one (PostLoginRedirect checked it).
     */
    public static function loginUrl(?string $next): string
    {
        return route('login', $next !== null ? ['next' => $next] : []);
    }
}
