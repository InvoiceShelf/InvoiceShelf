<?php

use App\Platform\Modules\Models\MarketplaceCredential;
use App\Platform\Modules\Models\MarketplaceOperation;
use App\Platform\Modules\Models\Module;
use App\Platform\Modules\ModuleServiceProvider;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

test('the module platform owns its provider and persistence models', function () {
    expect(app()->getProviders(ModuleServiceProvider::class))->not->toBeEmpty()
        ->and((new Module)->getTable())->toBe('modules')
        ->and((new MarketplaceCredential)->getTable())->toBe('marketplace_credentials')
        ->and((new MarketplaceOperation)->getTable())->toBe('marketplace_operations')
        ->and(class_exists('App\\Models\\Module'))->toBeFalse()
        ->and(class_exists('App\\Services\\Marketplace\\MarketplaceInstaller'))->toBeFalse();
});

test('the module platform preserves its public routes and middleware', function () {
    $routes = collect(Route::getRoutes())->filter(
        fn (IlluminateRoute $route): bool => $route->uri() === 'api/v1/company-modules'
            || str_starts_with($route->uri(), 'api/v1/modules')
            || str_starts_with($route->uri(), 'modules/scripts')
            || str_starts_with($route->uri(), 'modules/styles')
    );

    $signatures = $routes
        ->flatMap(fn (IlluminateRoute $route): array => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): string => "{$method} {$route->uri()}")
            ->all())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe(collect([
        'GET api/v1/company-modules',
        'GET api/v1/modules',
        'GET api/v1/modules/pairing',
        'POST api/v1/modules/pairing/start',
        'POST api/v1/modules/pairing/poll',
        'DELETE api/v1/modules/pairing',
        'GET api/v1/modules/{module}',
        'POST api/v1/modules/{module}/enable',
        'POST api/v1/modules/{module}/disable',
        'POST api/v1/modules/{module}/uninstall',
        'POST api/v1/modules/install',
        'GET api/v1/modules/{slug}/settings',
        'PUT api/v1/modules/{slug}/settings',
        'GET modules/scripts/{script}',
        'GET modules/styles/{style}',
    ])->sort()->values()->all());

    // Installing, removing and pairing belong to the hosting provider on a
    // managed install.
    $providerOwned = [
        'POST api/v1/modules/pairing/start',
        'POST api/v1/modules/pairing/poll',
        'DELETE api/v1/modules/pairing',
        'POST api/v1/modules/{module}/uninstall',
        'POST api/v1/modules/install',
    ];

    $routes->each(function (IlluminateRoute $route) use ($providerOwned): void {
        $expected = str_starts_with($route->uri(), 'api/')
            ? ['api', 'auth:sanctum', 'company']
            : ['web'];

        if (in_array($route->methods()[0].' '.$route->uri(), $providerOwned, true)) {
            $expected[] = 'not-managed';
        }

        expect($route->middleware())->toBe($expected)
            ->and($route->getActionName())->toStartWith('App\\Platform\\Modules\\');
    });
});
