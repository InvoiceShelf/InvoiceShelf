<?php

namespace App\Domains\Accounts\Application;

/**
 * Sign-in with SSO over OAuth 2.0 / OpenID Connect, as configured in
 * config/services.php: whether it is on, which scopes it asks for, and what a
 * login page needs to offer it.
 *
 * The browser gets that as `window.oauth2_login` from the Blade shell; the
 * mobile app sees `oauth2_login` in the client manifest and signs in through
 * a hand-off instead (OAuth2Handoffs).
 */
final class OAuth2Login
{
    /**
     * Session key that carries why a sign-in failed back to the login page:
     * "failed" or "no_account".
     */
    public const ERROR = 'oauth2_login_error';

    /**
     * Session key for the page to open after the sign-in, from the login
     * page's `next` (see PostLoginRedirect).
     */
    public const NEXT = 'oauth2_login_next';

    /**
     * Session key for the mobile app's hand-off a browser sign-in belongs to.
     */
    public const HANDOFF = 'oauth2_login_handoff';

    /**
     * The settings sign-in with SSO needs before it is offered.
     */
    private const REQUIRED = ['client_id', 'client_secret', 'authorize_url', 'token_url', 'userinfo_url'];

    /**
     * Sign-in with SSO is on once the client ID, the client secret and the
     * identity provider's three endpoint URLs are all set.
     */
    public static function enabled(): bool
    {
        foreach (self::REQUIRED as $setting) {
            if (blank(config("services.oauth2.{$setting}"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The scopes to ask the identity provider for, from OAUTH2_SCOPES
     * (separated by spaces or commas).
     *
     * @return list<string>
     */
    public static function scopes(): array
    {
        return preg_split('/[\s,]+/', (string) config('services.oauth2.scopes'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
            'url' => route('oauth2.redirect', [], false),
            'error' => session(self::ERROR),
        ];
    }
}
