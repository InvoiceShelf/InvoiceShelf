<?php

use App\Adapters\Money\EloquentExchangeRateBackfill;
use App\Domains\Money\Contracts\ExchangeRateBackfill;
use App\Domains\Money\ExchangeRates\CurrencyConverterDriver;
use App\Domains\Money\ExchangeRates\CurrencyFreakDriver;
use App\Domains\Money\Models\ExchangeRateProvider;
use App\Domains\Money\MoneyServiceProvider;
use App\Domains\Money\Policies\ExchangeRateProviderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvoiceShelf\Modules\Registry;

test('the money domain owns exchange-rate behavior and authorization', function () {
    expect(app()->getProviders(MoneyServiceProvider::class))->toHaveCount(1)
        ->and(app(ExchangeRateBackfill::class))->toBeInstanceOf(EloquentExchangeRateBackfill::class)
        ->and(Gate::getPolicyFor(ExchangeRateProvider::class))->toBeInstanceOf(ExchangeRateProviderPolicy::class)
        ->and(Registry::driverMeta('exchange_rate', 'currency_converter')['class'] ?? null)
        ->toBe(CurrencyConverterDriver::class)
        ->and(Registry::driverMeta('exchange_rate', 'currency_freak')['class'] ?? null)
        ->toBe(CurrencyFreakDriver::class);

    expect(class_exists('App\\Providers\\DriverRegistryProvider'))->toBeFalse()
        ->and(class_exists('App\\Support\\ExchangeRate\\ExchangeRateDriverFactory'))->toBeFalse()
        ->and(class_exists('App\\Services\\Document\\CurrencyService'))->toBeFalse()
        ->and(class_exists('App\\Services\\ExchangeRateProviderService'))->toBeFalse()
        ->and(class_exists('App\\Policies\\ExchangeRateProviderPolicy'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\CurrenciesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\ExchangeRate\\ExchangeRateProviderController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\ExchangeRateProviderRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CurrencyResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\CurrencyResource'))->toBeFalse();
});

test('the money domain preserves its public routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match(
            '#^api/v1/(?:currencies(?:$|/)|exchange-rate-providers(?:$|/)|supported-currencies$|used-currencies$)#',
            $route->uri(),
        ) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/exchange-rate-providers/{exchange_rate_provider}',
        'GET|HEAD api/v1/currencies',
        'GET|HEAD api/v1/currencies/used',
        'GET|HEAD api/v1/currencies/{currency}/active-provider',
        'GET|HEAD api/v1/currencies/{currency}/exchange-rate',
        'GET|HEAD api/v1/exchange-rate-providers',
        'GET|HEAD api/v1/exchange-rate-providers/{exchange_rate_provider}',
        'GET|HEAD api/v1/supported-currencies',
        'GET|HEAD api/v1/used-currencies',
        'POST api/v1/currencies/bulk-update-exchange-rate',
        'POST api/v1/exchange-rate-providers',
        'PUT|PATCH api/v1/exchange-rate-providers/{exchange_rate_provider}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Domains\\Money\\Http\\Controllers\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});

test('the money domain owns the installation-wide currency routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/super-admin/currencies'))
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe([
        'GET|HEAD api/v1/super-admin/currencies',
        'POST api/v1/super-admin/currencies/refresh',
    ]);

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Money\\Http\\Controllers\\Admin\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'super-admin');
    }
});
