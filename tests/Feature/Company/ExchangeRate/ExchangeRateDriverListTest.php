<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);
});

test('config endpoint returns built-in exchange rate drivers from the Registry', function () {
    $response = getJson('/api/v1/config?key=exchange_rate_drivers')->assertOk();

    $drivers = collect($response->json('exchange_rate_drivers'));

    expect($drivers->pluck('value')->all())
        ->toContain('currency_converter')
        ->toContain('currency_freak')
        ->toContain('currency_layer')
        ->toContain('open_exchange_rate');
});

test('built-in drivers carry label, website, and config_fields metadata', function () {
    $response = getJson('/api/v1/config?key=exchange_rate_drivers')->assertOk();

    $drivers = collect($response->json('exchange_rate_drivers'));

    $converter = $drivers->firstWhere('value', 'currency_converter');
    expect($converter['label'])->toBe('settings.exchange_rate.currency_converter')
        ->and($converter['website'])->toBe('https://www.currencyconverterapi.com')
        ->and($converter['config_fields'])->toBeArray()->not->toBeEmpty();

    // The Currency Converter driver declares two config_fields (server type + URL).
    // The URL field is conditionally visible when type=DEDICATED.
    $urlField = collect($converter['config_fields'])->firstWhere('key', 'url');
    expect($urlField['visible_when'])->toBe(['type' => 'DEDICATED']);

    $freak = $drivers->firstWhere('value', 'currency_freak');
    expect($freak['website'])->toBe('https://currencyfreaks.com')
        ->and($freak['config_fields'])->toBe([]);
});

test('module-registered exchange rate drivers appear in the config endpoint response', function () {
    Registry::registerExchangeRateDriver('test_module_driver', [
        'class' => 'Modules\\Test\\Drivers\\TestDriver',
        'label' => 'test_module::drivers.test',
        'website' => 'https://test.example.com',
    ]);

    try {
        $response = getJson('/api/v1/config?key=exchange_rate_drivers')->assertOk();

        $drivers = collect($response->json('exchange_rate_drivers'));
        $custom = $drivers->firstWhere('value', 'test_module_driver');

        expect($custom)->not->toBeNull()
            ->and($custom['label'])->toBe('test_module::drivers.test')
            ->and($custom['website'])->toBe('https://test.example.com');
    } finally {
        // Clean up our test-only registration without wiping the built-ins,
        // which would break sibling tests in the same process.
        unset(Registry::$drivers['exchange_rate']['test_module_driver']);
    }
});

test('non-driver config keys still fall through to config files', function () {
    // Sanity check that the controller refactor didn't break unrelated keys.
    $response = getJson('/api/v1/config?key=custom_field_models')->assertOk();

    expect($response->json('custom_field_models'))->toBeArray()->not->toBeEmpty();
});
