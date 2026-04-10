<?php

use App\Models\Module;
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

    Registry::flush();
});

afterEach(function () {
    Registry::flush();
});

test('returns only enabled modules', function () {
    Module::create([
        'name' => 'sales-tax-us',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
    ]);

    Module::create([
        'name' => 'archived-module',
        'version' => '0.5.0',
        'installed' => true,
        'enabled' => false,
    ]);

    $response = getJson('api/v1/company-modules')->assertOk();

    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.slug', 'sales-tax-us');
});

test('reports has_settings flag based on Registry', function () {
    Module::create([
        'name' => 'with-settings',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
    ]);

    Module::create([
        'name' => 'without-settings',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
    ]);

    Registry::registerSettings('with-settings', [
        'sections' => [
            ['title' => 'general', 'fields' => [
                ['key' => 'foo', 'type' => 'text'],
            ]],
        ],
    ]);

    $response = getJson('api/v1/company-modules')->assertOk();

    $rows = collect($response->json('data'))->keyBy('slug');

    expect($rows['with-settings']['has_settings'])->toBeTrue();
    expect($rows['without-settings']['has_settings'])->toBeFalse();
});

test('includes registered menu entry for active modules', function () {
    Module::create([
        'name' => 'menu-module',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
    ]);

    Registry::registerMenu('menu-module', [
        'title' => 'menu_module::menu.title',
        'link' => '/admin/modules/menu-module/settings',
        'icon' => 'CalculatorIcon',
    ]);

    $response = getJson('api/v1/company-modules')->assertOk();

    $response->assertJsonPath('data.0.display_name', 'Menu Module');
    $response->assertJsonPath('data.0.menu.title', 'menu_module::menu.title');
    $response->assertJsonPath('data.0.menu.icon', 'CalculatorIcon');
});

test('returns empty array when no modules are enabled', function () {
    getJson('api/v1/company-modules')
        ->assertOk()
        ->assertJsonPath('data', []);
});
