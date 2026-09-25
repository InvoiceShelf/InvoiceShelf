<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Requirements
    |--------------------------------------------------------------------------
    |
    | This is the default Laravel server requirements, you can add as many
    | as your application require, we check if the extension is enabled
    | by looping through the array and run "extension_loaded" on it.
    |
    */
    'core' => [
        'minPhpVersion' => '8.4.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Headless installation
    |--------------------------------------------------------------------------
    |
    | What `php artisan invoiceshelf:install` sets up when the web installer is
    | not used: the super administrator and the first company. Options given
    | on the command line win over these.
    |
    */
    'headless' => [
        'admin_name' => env('INSTALL_ADMIN_NAME', 'Administrator'),
        'admin_email' => env('INSTALL_ADMIN_EMAIL'),
        'admin_password' => env('INSTALL_ADMIN_PASSWORD'),
        // A file holding the password (a Docker or Kubernetes secret), read
        // when no password is given directly.
        'admin_password_file' => env('INSTALL_ADMIN_PASSWORD_FILE'),
        'company_name' => env('INSTALL_COMPANY_NAME', 'My Company'),
        'currency' => env('INSTALL_CURRENCY', 'USD'),
        'time_zone' => env('INSTALL_TIMEZONE', 'UTC'),
        'language' => env('INSTALL_LANGUAGE', 'en'),
        'date_format' => env('INSTALL_DATE_FORMAT'),
        'fiscal_year' => env('INSTALL_FISCAL_YEAR'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Setup wizard token lifetime
    |--------------------------------------------------------------------------
    |
    | Minutes the wizard's bearer token stays valid. The wizard asks for a new
    | one whenever it signs in again, so this only bounds a token nobody uses.
    |
    */
    'wizard_token_ttl' => (int) env('INSTALL_WIZARD_TOKEN_TTL', 120),

    'final' => [
        'key' => true,
        'publish' => false,
    ],
    'requirements' => [
        'php' => [
            'exif',
            'pdo',
            'bcmath',
            'openssl',
            'mbstring',
            'json',
            'xml',
            'fileinfo',
            'zip',
            'curl',
            'sqlite3',
        ],
        'apache' => [
            'mod_rewrite',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folders Permissions
    |--------------------------------------------------------------------------
    |
    | This is the default Laravel folders permissions, if your application
    | requires more permissions just add them to the array list below.
    |
    */
    'permissions' => [
        'storage/framework/' => '775',
        'storage/logs/' => '775',
        'bootstrap/cache/' => '775',
    ],
];
