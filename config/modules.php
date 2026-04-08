<?php

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Providers\ConsoleServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | Default module namespace.
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Vapor Maintenance Mode
    |--------------------------------------------------------------------------
    |
    | Indicates if the application is running on Laravel Vapor.
    | When enabled, cached services path will be set to a writable location.
    |
    */
    'vapor_maintenance_mode' => env('VAPOR_MAINTENANCE_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Module Stubs
    |--------------------------------------------------------------------------
    |
    | Default module stubs.
    |
    */
    'stubs' => [
        'enabled' => false,
        'path' => base_path('vendor/nwidart/laravel-modules/src/Commands/stubs'),
        'files' => [
            'routes/web' => 'routes/web.php',
            'routes/api' => 'routes/api.php',
            'views/index' => 'resources/views/index.blade.php',
            'views/master' => 'resources/views/components/layouts/master.blade.php',
            'scaffold/config' => 'config/config.php',
            'composer' => 'composer.json',
            'assets/js/app' => 'resources/assets/js/app.js',
            'assets/sass/app' => 'resources/assets/sass/app.scss',
            'vite' => 'vite.config.js',
            'package' => 'package.json',

            // InvoiceShelf stubs (shipped by the invoiceshelf/modules package)
            // — starter i18n files referenced by the registered menu title and
            // settings schema.
            'lang/menu' => 'lang/en/menu.php',
            'lang/settings' => 'lang/en/settings.php',
        ],
        'replacements' => [
            'routes/web' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'routes/api' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'vite' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'json' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'PROVIDER_NAMESPACE'],
            'views/index' => ['LOWER_NAME'],
            'views/master' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'scaffold/config' => ['STUDLY_NAME'],
            'composer' => [
                'LOWER_NAME',
                'STUDLY_NAME',
                'VENDOR',
                'AUTHOR_NAME',
                'AUTHOR_EMAIL',
                'MODULE_NAMESPACE',
                'PROVIDER_NAMESPACE',
                'APP_FOLDER_NAME',
            ],

            // InvoiceShelf lang stubs — only need the module name tokens.
            'lang/menu' => ['STUDLY_NAME', 'LOWER_NAME'],
            'lang/settings' => ['STUDLY_NAME', 'LOWER_NAME'],
        ],
        'gitkeep' => true,
    ],

    'paths' => [
        'modules' => base_path('Modules'),
        'assets' => public_path('modules'),
        'migration' => base_path('database/migrations'),
        'app_folder' => 'app/',
        'generator' => [
            // app/
            'actions' => ['path' => 'app/Actions', 'generate' => false],
            'casts' => ['path' => 'app/Casts', 'generate' => false],
            'channels' => ['path' => 'app/Broadcasting', 'generate' => false],
            'class' => ['path' => 'app/Classes', 'generate' => false],
            'command' => ['path' => 'app/Console', 'generate' => false],
            'command_replacements' => ['path' => 'app/Console/Replacements', 'generate' => false],
            'component-class' => ['path' => 'app/View/Components', 'generate' => false],
            'emails' => ['path' => 'app/Emails', 'generate' => false],
            'event' => ['path' => 'app/Events', 'generate' => false],
            'enums' => ['path' => 'app/Enums', 'generate' => false],
            'exceptions' => ['path' => 'app/Exceptions', 'generate' => false],
            'jobs' => ['path' => 'app/Jobs', 'generate' => false],
            'helpers' => ['path' => 'app/Helpers', 'generate' => false],
            'interfaces' => ['path' => 'app/Interfaces', 'generate' => false],
            'listener' => ['path' => 'app/Listeners', 'generate' => false],
            'model' => ['path' => 'app/Models', 'generate' => false],
            'notifications' => ['path' => 'app/Notifications', 'generate' => false],
            'observer' => ['path' => 'app/Observers', 'generate' => false],
            'policies' => ['path' => 'app/Policies', 'generate' => false],
            'provider' => ['path' => 'app/Providers', 'generate' => true],
            'repository' => ['path' => 'app/Repositories', 'generate' => false],
            'resource' => ['path' => 'app/Transformers', 'generate' => false],
            'route-provider' => ['path' => 'app/Providers', 'generate' => true],
            'rules' => ['path' => 'app/Rules', 'generate' => false],
            'services' => ['path' => 'app/Services', 'generate' => false],
            'scopes' => ['path' => 'app/Models/Scopes', 'generate' => false],
            'traits' => ['path' => 'app/Traits', 'generate' => false],

            // app/Http/
            'controller' => ['path' => 'app/Http/Controllers', 'generate' => true],
            'filter' => ['path' => 'app/Http/Middleware', 'generate' => false],
            'request' => ['path' => 'app/Http/Requests', 'generate' => false],

            // config/
            'config' => ['path' => 'config', 'generate' => true],

            // database/
            'factory' => ['path' => 'database/factories', 'generate' => true],
            'migration' => ['path' => 'database/migrations', 'generate' => true],
            'seeder' => ['path' => 'database/seeders', 'generate' => true],

            // lang/
            'lang' => ['path' => 'lang', 'generate' => false],

            // resource/
            'assets' => ['path' => 'resources/assets', 'generate' => true],
            'component-view' => ['path' => 'resources/views/components', 'generate' => false],
            'views' => ['path' => 'resources/views', 'generate' => true],
            'inertia' => ['path' => 'resources/js/Pages', 'generate' => false],
            'inertia-components' => ['path' => 'resources/js/Components', 'generate' => false],

            // routes/
            'routes' => ['path' => 'routes', 'generate' => true],

            // tests/
            'test-feature' => ['path' => 'tests/Feature', 'generate' => true],
            'test-unit' => ['path' => 'tests/Unit', 'generate' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Discover of Modules
    |--------------------------------------------------------------------------
    */
    'auto-discover' => [
        'migrations' => true,
        'translations' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package commands
    |--------------------------------------------------------------------------
    |
    | Built dynamically from upstream nwidart's default command list. Add new
    | commands to the merge() block below.
    |
    */
    'commands' => ConsoleServiceProvider::defaultCommands()
        ->merge([
            // InvoiceShelf-specific module commands go here
        ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Scan Path
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'enabled' => false,
        'paths' => [
            base_path('vendor/*/*'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer File Template
    |--------------------------------------------------------------------------
    */
    'composer' => [
        'vendor' => env('MODULE_VENDOR', 'invoiceshelf'),
        'author' => [
            'name' => env('MODULE_AUTHOR_NAME', 'InvoiceShelf'),
            'email' => env('MODULE_AUTHOR_EMAIL', 'hello@invoiceshelf.com'),
        ],
        'composer-output' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Choose what laravel-modules will register as custom namespaces.
    |--------------------------------------------------------------------------
    */
    'register' => [
        'translations' => true,
        'files' => 'register',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activators
    |--------------------------------------------------------------------------
    |
    | InvoiceShelf-specific override: keep the statuses file under storage/app/
    | so existing installations don't lose track of which modules are enabled
    | when this config is republished. Upstream v13 defaults to base_path() but
    | the v3 ModuleInstaller already writes here.
    */
    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('storage/app/modules_statuses.json'),
            'cache-key' => 'activator.installed',
            'cache-lifetime' => 604800,
        ],
    ],

    'activator' => 'file',

    /*
    |--------------------------------------------------------------------------
    | Inertia
    |--------------------------------------------------------------------------
    */
    'inertia' => [
        'frontend' => 'vue',
    ],
];
