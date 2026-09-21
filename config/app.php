<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone for date and date-time functions. Laravel's own
    | fallback is the literal string 'UTC' rather than an env() lookup, so
    | without this key APP_TIMEZONE has no effect at all and scheduled tasks
    | and recurring invoices always run on UTC.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | Absolute URLs take their scheme from the incoming request, which behind a
    | reverse proxy only says "https" when the proxy's own address is listed in
    | TRUSTED_PROXIES. Inside a container that address is the bridge gateway,
    | not the proxy's LAN address, so a plausible-looking list silently yields
    | http:// redirects after login. Leave this unset and an https APP_URL is
    | taken as the intent; set it explicitly to override either way.
    |
    */

    'force_https' => env('FORCE_HTTPS'),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. You may add any additional class aliases which should
    | be loaded to the array. For speed, all aliases are lazy loaded.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Menu' => Lavary\Menu\Facade::class,
    ])->toArray(),

];
