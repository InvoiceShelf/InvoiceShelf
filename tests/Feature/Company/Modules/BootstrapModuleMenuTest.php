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

    Registry::flush();
});

afterEach(function () {
    Registry::flush();
});

test('bootstrap returns module_menu populated from Registry', function () {
    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us/settings',
        'icon' => 'CalculatorIcon',
    ]);

    $response = getJson('api/v1/bootstrap')->assertOk();

    $response->assertJsonPath('module_menu.0.title', 'sales_tax_us::menu.title');
    $response->assertJsonPath('module_menu.0.link', '/admin/modules/sales-tax-us/settings');
    $response->assertJsonPath('module_menu.0.icon', 'CalculatorIcon');
});

test('bootstrap returns empty module_menu when nothing is registered', function () {
    getJson('api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('module_menu', []);
});

test('admin-mode bootstrap does not include module_menu', function () {
    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us/settings',
        'icon' => 'CalculatorIcon',
    ]);

    $response = getJson('api/v1/bootstrap?admin_mode=1');

    // Super-admin branch should not include the dynamic Modules sidebar group —
    // that surface only exists in the company context.
    $response->assertJsonMissingPath('module_menu');
});
