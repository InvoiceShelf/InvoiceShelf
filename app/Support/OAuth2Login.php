<?php

namespace App\Support;

/**
 * Sign-in with SSO over OAuth 2.0 / OpenID Connect: whether it is set up, and
 * what the login page needs to offer it. config/services.php has the settings.
 */
final class OAuth2Login
{
    /**
     * Session key that carries the reason a sign-in failed back to the login
     * page: "failed" or "no_account".
     */
    public const ERROR = 'oauth2_login_error';

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
     * did. Null while sign-in with SSO is off.
     *
     * @return array{url: string, error: string|null}|null
     */
    public static function forLoginPage(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        return [
            'url' => route('oauth2.redirect', [], false),
            'error' => session(self::ERROR),
        ];
    }
}
