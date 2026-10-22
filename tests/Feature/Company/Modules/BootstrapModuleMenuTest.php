<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

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

test('bootstrap merges module menu items into main_menu', function () {
    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us/settings',
        'icon' => 'CalculatorIcon',
    ]);

    $response = getJson('api/v1/bootstrap')->assertOk();

    $mainMenu = collect($response->json('main_menu'));
    $moduleItem = $mainMenu->firstWhere('name', 'module-sales-tax-us');

    expect($moduleItem)->not->toBeNull();
    expect($moduleItem['link'])->toBe('/admin/modules/sales-tax-us/settings');
    expect($moduleItem['icon'])->toBe('CalculatorIcon');
    expect($moduleItem['group'])->toBe('modules');
});

test('module items support custom group and priority', function () {
    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us/settings',
        'icon' => 'CalculatorIcon',
        'group' => 'documents',
        'priority' => 25,
    ]);

    $response = getJson('api/v1/bootstrap')->assertOk();

    $mainMenu = collect($response->json('main_menu'));
    $moduleItem = $mainMenu->firstWhere('name', 'module-sales-tax-us');

    expect($moduleItem['group'])->toBe('documents');
    expect($moduleItem['priority'])->toBe(25);
});

test('module items with an ability are hidden until the user can open the route', function () {
    $company = User::findOrFail(1)->companies()->firstOrFail();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id);
    Sanctum::actingAs($user, ['*']);

    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us',
        'icon' => 'CalculatorIcon',
        'ability' => 'sales-tax-us:view',
    ]);

    $withoutAbility = collect(getJson('api/v1/bootstrap')->assertOk()->json('main_menu'));

    expect($withoutAbility->firstWhere('name', 'module-sales-tax-us'))->toBeNull();

    BouncerFacade::scope()->onceTo(
        $company->id,
        fn () => BouncerFacade::allow($user)->to('sales-tax-us:view')
    );
    BouncerFacade::refreshFor($user);

    $withAbility = collect(getJson('api/v1/bootstrap')->assertOk()->json('main_menu'));

    expect($withAbility->firstWhere('name', 'module-sales-tax-us'))->not->toBeNull();
});

test('bootstrap has no module items when nothing is registered', function () {
    $response = getJson('api/v1/bootstrap')->assertOk();

    $mainMenu = collect($response->json('main_menu'));
    $moduleItems = $mainMenu->filter(fn ($item) => str_starts_with($item['name'], 'module-'));

    expect($moduleItems)->toBeEmpty();
});

test('admin-mode bootstrap does not include module items', function () {
    Registry::registerMenu('sales-tax-us', [
        'title' => 'sales_tax_us::menu.title',
        'link' => '/admin/modules/sales-tax-us/settings',
        'icon' => 'CalculatorIcon',
    ]);

    $response = getJson('api/v1/bootstrap?admin_mode=1');

    $mainMenu = collect($response->json('main_menu'));
    $moduleItems = $mainMenu->filter(fn ($item) => str_starts_with($item['name'] ?? '', 'module-'));

    expect($moduleItems)->toBeEmpty();
});
