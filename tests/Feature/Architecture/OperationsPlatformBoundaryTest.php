<?php

use App\Platform\Operations\Http\Middleware\CronJobMiddleware;
use App\Platform\Operations\Http\Middleware\EnsureNotContainerized;
use App\Platform\Operations\Installation\Http\Middleware\EnsureInstalled;
use App\Platform\Operations\Installation\Http\Middleware\RedirectIfInstalled;
use App\Platform\Operations\Installation\Http\Middleware\UseInstallWizardTokenAuth;
use App\Platform\Operations\OperationsServiceProvider;
use App\Platform\Storage\Application\FileDiskService;
use App\Platform\Storage\Contracts\StorageConfigurator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the operations platform owns runtime configuration commands and authorization', function () {
    expect(app()->getProviders(OperationsServiceProvider::class))->toHaveCount(1)
        ->and(app(StorageConfigurator::class))->toBeInstanceOf(FileDiskService::class)
        ->and(Gate::has('manage settings'))->toBeTrue()
        ->and(Gate::has('manage update app'))->toBeTrue()
        ->and(Artisan::all())->toHaveKeys(['core:update', 'reset:app']);

    expect(class_exists('App\\Providers\\AppConfigProvider'))->toBeFalse()
        ->and(class_exists('App\\Support\\Update\\Updater'))->toBeFalse()
        ->and(class_exists('App\\Console\\Commands\\UpdateCommand'))->toBeFalse()
        ->and(class_exists('App\\Console\\Commands\\ResetApp'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\AppVersionController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\UpdateController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\Settings\\SettingsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\AdminDashboardController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\General\\BootstrapController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\General\\ConfigController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\General\\FormatsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Webhook\\CronJobController'))->toBeFalse()
        ->and(class_exists('App\\Support\\Setup\\InstallUtils'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Setup\\LoginController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Middleware\\InstallationMiddleware'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\DatabaseEnvironmentRequest'))->toBeFalse();
});

test('the operations platform owns bootstrap configuration and admin diagnostics routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array($route->uri(), [
            'api/v1/bootstrap',
            'api/v1/config',
            'api/v1/current-company',
            'api/v1/date/formats',
            'api/v1/super-admin/dashboard',
            'api/v1/time/formats',
            'api/v1/timezones',
        ], true))
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'GET|HEAD api/v1/bootstrap',
        'GET|HEAD api/v1/config',
        'GET|HEAD api/v1/current-company',
        'GET|HEAD api/v1/date/formats',
        'GET|HEAD api/v1/super-admin/dashboard',
        'GET|HEAD api/v1/time/formats',
        'GET|HEAD api/v1/timezones',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Operations\\Http\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum');
    }

    expect($routes->get('GET|HEAD api/v1/super-admin/dashboard')->gatherMiddleware())
        ->toContain('super-admin')
        ->and($routes->except('GET|HEAD api/v1/super-admin/dashboard')->every(
            fn ($route): bool => in_array('company', $route->gatherMiddleware(), true)
                && in_array('bouncer', $route->gatherMiddleware(), true),
        ))->toBeTrue();
});

test('the operations platform preserves its public routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match(
            '#^api/(?:v1/(?:app/(?:version|client-manifest)|settings$|check/update$|update/)|cron$)#',
            $route->uri(),
        ) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'GET|HEAD api/cron',
        'GET|HEAD api/v1/app/client-manifest',
        'GET|HEAD api/v1/app/version',
        'GET|HEAD api/v1/check/update',
        'GET|HEAD api/v1/settings',
        'POST api/v1/settings',
        'POST api/v1/update/clean',
        'POST api/v1/update/copy',
        'POST api/v1/update/delete',
        'POST api/v1/update/download',
        'POST api/v1/update/finish',
        'POST api/v1/update/migrate',
        'POST api/v1/update/unzip',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Operations\\Http\\');
    }

    foreach (['GET|HEAD api/v1/settings', 'POST api/v1/settings'] as $key) {
        expect($routes->get($key)->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }

    foreach ($routes->filter(fn ($route) => str_contains($route->uri(), 'update')) as $route) {
        expect($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'not-containerized');
    }

    expect($routes->get('GET|HEAD api/cron')->gatherMiddleware())->toContain('cron-job')
        ->and(app('router')->getMiddleware()['cron-job'] ?? null)->toBe(CronJobMiddleware::class)
        ->and(app('router')->getMiddleware()['not-containerized'] ?? null)->toBe(EnsureNotContainerized::class);
});

test('the operations platform owns installation routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/installation/')
            || str_starts_with($route->uri(), 'installation'))
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'GET|HEAD api/v1/installation/database/config',
        'GET|HEAD api/v1/installation/languages',
        'GET|HEAD api/v1/installation/permissions',
        'GET|HEAD api/v1/installation/requirements',
        'GET|HEAD api/v1/installation/wizard-step',
        'GET|HEAD installation',
        'GET|HEAD installation/{vue?}',
        'POST api/v1/installation/database/config',
        'POST api/v1/installation/finish',
        'POST api/v1/installation/login',
        'POST api/v1/installation/wizard-language',
        'POST api/v1/installation/wizard-step',
        'POST installation/session-login',
        'PUT api/v1/installation/set-domain',
    ])->sort()->values()->all());

    foreach ($routes->filter(fn ($route) => str_starts_with($route->uri(), 'api/')) as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Platform\\Operations\\Installation\\Http\\Controllers\\')
            ->and($route->gatherMiddleware())->toContain('redirect-if-installed');
    }

    expect($routes->get('POST installation/session-login')->getActionName())
        ->toBe('App\\Platform\\Operations\\Installation\\Http\\Controllers\\SessionLoginController')
        ->and(app('router')->getMiddleware()['install'] ?? null)->toBe(EnsureInstalled::class)
        ->and(app('router')->getMiddleware()['redirect-if-installed'] ?? null)->toBe(RedirectIfInstalled::class)
        ->and(app()->make(UseInstallWizardTokenAuth::class))->toBeInstanceOf(UseInstallWizardTokenAuth::class);
});
