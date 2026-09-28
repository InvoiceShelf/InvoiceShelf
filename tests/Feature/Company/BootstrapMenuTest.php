<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    // Navigation is only registered at boot once the schema exists, which the
    // in-memory test database does not until after boot.
    app()->getProvider(AppServiceProvider::class)->addMenus();
});

test('owner-only navigation survives the first bootstrap after login', function () {
    // The SPA only learns which workspace it is in from this response, so the
    // call it makes right after login carries no company header. A platform
    // administrator is exempt from the company middleware's header rewrite,
    // which used to leave the owner gate on navigation entries unmet.
    $user = User::findOrFail(1);
    $company = $user->companies()->firstOrFail();

    expect($user->isSuperAdmin())->toBeTrue();
    expect($company->owner_id)->toBe($user->id);

    Sanctum::actingAs($user, ['*']);

    $withoutHeader = getJson('/api/v1/bootstrap')->assertOk();
    $withHeader = $this->withHeaders(['company' => $company->id])
        ->getJson('/api/v1/bootstrap')
        ->assertOk();

    expect($withoutHeader->json('current_company.id'))->toBe($company->id);
    expect(collect($withoutHeader->json('main_menu'))->pluck('name')->all())
        ->toContain('Members');
    expect($withoutHeader->json('main_menu'))->toEqual($withHeader->json('main_menu'));
    expect($withoutHeader->json('setting_menu'))->toEqual($withHeader->json('setting_menu'));
});

test('sales and purchases each expose four primary destinations', function () {
    Sanctum::actingAs(User::findOrFail(1), ['*']);
    $menu = collect(getJson('/api/v1/bootstrap')->assertOk()->json('main_menu'));

    expect($menu->where('group', 'documents')->sortBy('priority')->pluck('name')->values()->all())
        ->toBe(['Customers', 'Estimates', 'Invoices', 'Payments']);
    expect($menu->where('group', 'purchases')->sortBy('priority')->pluck('name')->values()->all())
        ->toBe(['Supplier', 'Bill', 'Expenses', 'SupplierPayment']);
});

test('secondary purchase permissions keep their parent navigation accessible', function (string $ability, string $model, array $expected) {
    $company = User::findOrFail(1)->companies()->firstOrFail();
    $user = User::factory()->create(['role' => 'customer']);
    $user->companies()->attach($company->id);
    BouncerFacade::scope()->to($company->id);
    BouncerFacade::allow($user)->to($ability, $model);
    BouncerFacade::refresh();
    Sanctum::actingAs($user);

    $menu = collect($this->withHeaders(['company' => $company->id])->getJson('/api/v1/bootstrap')->assertOk()->json('main_menu'));
    expect($menu->where('group', 'purchases')->pluck('name')->sort()->values()->all())->toBe($expected);
})->with([
    ['view-supplier-credit', SupplierCredit::class, ['Bill']],
    ['view-supplier-refund', SupplierRefund::class, ['SupplierPayment']],
    ['view-recurring-cost', RecurringCost::class, ['Bill', 'Expenses']],
]);
