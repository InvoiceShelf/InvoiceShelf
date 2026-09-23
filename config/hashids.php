<?php

/**
 * Hashids connection config (per model class).
 *
 * Only companies are still addressed by a Hashid, and only in URLs that also
 * need a signed-in member. Hashids read just the start of a salt, so the
 * APP_KEY appended here does not make them secret. Links that open a document
 * without signing in use App\Support\PublicToken instead.
 *
 * Wired by App\Support\Hashids\HashidsServiceProvider using the hashids/hashids package.
 */

use App\Support\Hashids\HashidConnection;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Hashids Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */

    'connections' => [
        HashidConnection::Company->value => [
            'salt' => 'App\\Models\\Company'.config('app.key'),
            'length' => 20,
            'alphabet' => 's0D7xOFYEqn2uKJm3Pr9g8Cz46A1iHLBTVW5',
        ],
    ],
];
