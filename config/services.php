<?php

use App\Models\User;

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
     * crontab entry. Leave it unset and the endpoint refuses everything.
     */
    'cron_job' => [
        'auth_token' => env('CRON_JOB_AUTH_TOKEN'),
    ],

    /*
     * Sign-in with SSO over OAuth 2.0 / OpenID Connect, with any identity
     * provider that has a userinfo endpoint (Authentik, Keycloak, Authelia,
     * Zitadel, ...). It is on once the client ID, the client secret and the
     * three endpoint URLs are set. The provider's
     * /.well-known/openid-configuration lists those URLs as
     * authorization_endpoint, token_endpoint and userinfo_endpoint. The
     * redirect URI defaults to /auth/oauth2/callback on whatever host the
     * login page was opened on.
     */
    'oauth2' => [
        'client_id' => env('OAUTH2_CLIENT_ID'),
        'client_secret' => env('OAUTH2_CLIENT_SECRET'),
        'authorize_url' => env('OAUTH2_AUTHORIZE_URL'),
        'token_url' => env('OAUTH2_TOKEN_URL'),
        'userinfo_url' => env('OAUTH2_USERINFO_URL'),
        'scopes' => env('OAUTH2_SCOPES', 'openid profile email'),
        'redirect' => env('OAUTH2_REDIRECT_URI', '/auth/oauth2/callback'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

];
