<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Published so thin clients (the Capacitor mobile apps) can reach this
| installation from their own origin. A browser on the same origin as the
| server never consults any of this, so the web SPA is unaffected.
|
| The listed paths are everything a client talks to: the JSON API, the module
| assets it loads at runtime the way the Blade shell does, the report
| endpoints, the web-served PDF routes and the MCP endpoint.
|
| Credentials stay off on purpose. Clients authenticate with a bearer token,
| never with the session cookie, and allowing credentials here would force a
| single origin per response for no gain.
|
*/

$client_hostname = env('INVOICESHELF_CLIENT_HOSTNAME', 'app.invoiceshelf.internal');

$configured_origins = array_values(array_filter(
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    fn (string $origin): bool => $origin !== '',
));

return [

    'paths' => [
        'api/*',
        'modules/scripts/*',
        'modules/styles/*',
        'reports/*',
        'invoices/pdf/*',
        'estimates/pdf/*',
        'payments/pdf/*',
        'mcp',
    ],

    'allowed_methods' => ['*'],

    /*
     * Comma separated in CORS_ALLOWED_ORIGINS. Unset, the two origins a
     * Capacitor client presents are allowed: the custom scheme iOS uses and
     * the https scheme Android serves the bundle over.
     */
    'allowed_origins' => $configured_origins !== [] ? $configured_origins : [
        'capacitor://'.$client_hostname,
        'https://'.$client_hostname,
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'company',
        'Content-Type',
        'Accept',
        'X-Requested-With',
    ],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 7200,

    'supports_credentials' => false,

];
