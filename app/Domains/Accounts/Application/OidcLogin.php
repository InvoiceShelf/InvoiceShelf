<?php

namespace App\Domains\Accounts\Application;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

/**
 * Single sign-on over OpenID Connect, as configured in config/services.php
 * (`oidc`, the OIDC_* settings): whether it is set up, the issuer, which
 * scopes it asks for, and the Socialite driver that speaks it.
 *
 * Nothing here talks to the identity provider. The login page and the client
 * manifest ask only whether to offer the button, and that must not wait on
 * the network; the endpoints are found when a sign-in starts (OidcProvider).
 */
final class OidcLogin
{
    /**
     * It is set up once the client ID and the client secret are set, and
     * either the issuer (OIDC_ISSUER, whose discovery document lists the
     * endpoints) or all three endpoint URLs.
     */
    public static function enabled(): bool
    {
        if (blank(config('services.oidc.client_id')) || blank(config('services.oidc.client_secret'))) {
            return false;
        }

        if (self::issuer() !== null) {
            return true;
        }

        foreach (['authorize_url', 'token_url', 'userinfo_url'] as $endpoint) {
            if (blank(config("services.oidc.{$endpoint}"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The configured issuer (OIDC_ISSUER) without its trailing slash, or null
     * when there is none. Authentik's issuers end with a slash and
     * Keycloak's do not, so issuers are compared without it.
     */
    public static function issuer(): ?string
    {
        $issuer = trim((string) config('services.oidc.issuer'));

        return $issuer === '' ? null : self::withoutTrailingSlash($issuer);
    }

    /**
     * The URL with one trailing slash removed, if it has one.
     */
    public static function withoutTrailingSlash(string $url): string
    {
        return str_ends_with($url, '/') ? substr($url, 0, -1) : $url;
    }

    /**
     * Whether the first sign-in of an account, which links it to a user by
     * email address, needs the identity provider to say it verified that
     * address (OIDC_REQUIRE_VERIFIED_EMAIL, on unless set to false).
     */
    public static function requiresVerifiedEmail(): bool
    {
        $setting = config('services.oidc.require_verified_email');

        return $setting === null || filter_var($setting, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false;
    }

    /**
     * The scopes to ask the identity provider for, from OIDC_SCOPES
     * (separated by spaces or commas).
     *
     * @return list<string>
     */
    public static function scopes(): array
    {
        return preg_split('/[\s,]+/', (string) config('services.oidc.scopes'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Socialite's "oidc" driver (OidcProvider), asking for the scopes in
     * OIDC_SCOPES, with PKCE, and with these extra authorization request
     * parameters (none unless given).
     *
     * @param  array<string, string>  $parameters
     */
    public static function provider(array $parameters = []): Provider
    {
        return Socialite::driver('oidc')
            ->setScopes(self::scopes())
            ->enablePKCE()
            ->with($parameters);
    }
}
