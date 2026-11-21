<?php

use App\Domains\Accounts\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => env('MAILGUN_SCHEME', 'https'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    'stripe' => [
        'model' => User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    /*
     * Shared secret for the cron webhook at GET /api/cron, which lets an
     * external scheduler drive Laravel's own on hosts that cannot register a
     * crontab entry. Leave it unset and the endpoint refuses everything, which
     * is the right default for installs that run a real scheduler.
     */
    'cron_job' => [
        'auth_token' => env('CRON_JOB_AUTH_TOKEN'),
    ],

    /*
     * Single sign-on over OpenID Connect, with any OpenID Connect identity
     * provider that has a userinfo endpoint (Keycloak, Authentik, Authelia,
     * Zitadel, ...). It is on once the client ID and the client secret are
     * set, and either the issuer or all three endpoint URLs. With the
     * issuer, the endpoints come from its /.well-known/openid-configuration;
     * an endpoint URL that is set wins over the discovered one.
     *
     * client_auth is how the client authenticates at the token endpoint:
     * "post" (the secret in the request body), "basic" (an Authorization
     * header) or "auto" (post, unless the discovery document says the token
     * endpoint takes basic but not post). require_verified_email makes the
     * first sign-in, which links an account to a user by email address, need
     * the provider's email_verified claim to be true. The redirect URI
     * defaults to /auth/oidc/callback on whatever host the sign-in started
     * on. guzzle is the HTTP client's options for every request to the
     * provider: 5 seconds to connect and 10 for an answer (discovery allows
     * 5 in all). See docs/features/single-sign-on.md.
     */
    'oidc' => [
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'issuer' => env('OIDC_ISSUER'),
        'authorize_url' => env('OIDC_AUTHORIZE_URL'),
        'token_url' => env('OIDC_TOKEN_URL'),
        'userinfo_url' => env('OIDC_USERINFO_URL'),
        'client_auth' => env('OIDC_CLIENT_AUTH', 'auto'),
        'require_verified_email' => env('OIDC_REQUIRE_VERIFIED_EMAIL', true),
        'scopes' => env('OIDC_SCOPES', 'openid profile email'),
        'redirect' => env('OIDC_REDIRECT_URI', '/auth/oidc/callback'),
        'guzzle' => [
            'connect_timeout' => 5,
            'timeout' => 10,
        ],
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

];
