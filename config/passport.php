<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consent guard
    |--------------------------------------------------------------------------
    |
    | The guard whose session approves an authorization request. Consent is
    | only ever given from a signed-in browser, never with a password grant,
    | so whatever protects the session sign-in (and later SSO or a second
    | factor) protects every grant as well.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    |
    | Applied to every route Passport registers under /oauth. The server
    | answers 404 until a feature that uses it (the MCP server, for one) has
    | been switched on.
    |
    */

    'middleware' => ['oauth.enabled'],

    /*
    |--------------------------------------------------------------------------
    | Signing keys
    |--------------------------------------------------------------------------
    |
    | Taken from these variables when set, which is what a deployment with
    | more than one replica should do. Otherwise read from
    | storage/oauth-private.key and storage/oauth-public.key, which
    | `php artisan oauth:keys` writes.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token lifetimes
    |--------------------------------------------------------------------------
    |
    | Access tokens in minutes, refresh tokens in days. Passport's own default
    | is a year for both, which is far too long for a token an AI client
    | holds.
    |
    */

    'access_token_minutes' => (int) env('OAUTH_ACCESS_TOKEN_MINUTES', 60),

    'refresh_token_days' => (int) env('OAUTH_REFRESH_TOKEN_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
