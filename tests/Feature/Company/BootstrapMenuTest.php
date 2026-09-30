<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Application\UserCompanyAccessService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Domains\Sales\Models\Invoice;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

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
]);

test('company roles take precedence over global roles in the active company', function () {
    $company = User::findOrFail(1)->companies()->firstOrFail();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id);
    app(UserCompanyAccessService::class)->syncUserAccess($user, ['manager'], []);

    $roleName = BouncerFacade::scope()->onceTo($company->id, function (): string {
        $role = Role::query()->create(['name' => 'invoice-viewer', 'title' => 'Invoice viewer']);
        BouncerFacade::allow($role)->to('view-invoice', Invoice::class);

        return $role->name;
    });
    app(UserCompanyAccessService::class)->replaceCompanyRoles($user, $company->id, [$roleName]);

    expect($user->globalRolePresets()->pluck('key')->all())->toBe(['manager'])
        ->and(DB::table('assigned_roles')
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->id)
            ->whereNull('scope')
            ->exists())->toBeTrue();

    Sanctum::actingAs($user);

    $response = $this->withHeader('company', $company->id)
        ->getJson('/api/v1/bootstrap')
        ->assertOk();

    expect(collect($response->json('current_user_abilities'))->pluck('name')->all())
        ->toContain('view-invoice')
        ->not->toContain('dashboard', 'view-customer', 'create-invoice')
        ->and(collect($response->json('main_menu'))->pluck('name')->all())
        ->toContain('Invoices')
        ->not->toContain('Dashboard', 'Customers');
});

test('company roles can explicitly combine with global roles in the active company', function () {
    $company = User::findOrFail(1)->companies()->firstOrFail();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id, ['include_global_roles' => true]);
    app(UserCompanyAccessService::class)->syncUserAccess($user, ['manager'], []);

    $roleName = BouncerFacade::scope()->onceTo($company->id, function (): string {
        $role = Role::query()->create(['name' => 'invoice-viewer', 'title' => 'Invoice viewer']);
        BouncerFacade::allow($role)->to('view-invoice', Invoice::class);

        return $role->name;
    });
    app(UserCompanyAccessService::class)->replaceCompanyRoles($user, $company->id, [$roleName]);
    Sanctum::actingAs($user);

    $response = $this->withHeader('company', $company->id)
        ->getJson('/api/v1/bootstrap')
        ->assertOk();

    expect(collect($response->json('current_user_abilities'))->pluck('name')->all())
        ->toContain('view-invoice', 'dashboard', 'view-customer', 'create-invoice')
        ->and(collect($response->json('main_menu'))->pluck('name')->all())
        ->toContain('Dashboard', 'Customers', 'Invoices');
});

test('global manager can use company resource endpoints after selecting a company', function () {
    $owner = User::factory()->create(['role' => 'user']);
    $company = Company::factory()->create(['owner_id' => $owner->id]);
    app(CompanyService::class)->setupDefaults($company);
    $user = User::factory()->create(['role' => 'user']);

    app(UserCompanyAccessService::class)->syncUserAccess($user, ['manager'], []);
    Sanctum::actingAs($user);

    $this->withHeader('company', $company->id)
        ->getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('current_company.id', $company->id);

    expect(collect($user->getAbilities())->pluck('name')->all())
        ->toContain('view-customer', 'create-customer');

    $this->withHeader('company', $company->id)
        ->getJson('/api/v1/customers')
        ->assertOk();

    $this->withHeader('company', $company->id)
        ->postJson('/api/v1/customers', [
            'name' => 'Global Role Customer',
            'email' => 'global-role-customer@example.com',
            'currency_id' => $company->currency_id,
            'enable_portal' => false,
        ])
        ->assertOk();

    expect(Customer::query()->where('name', 'Global Role Customer')->where('company_id', $company->id)->exists())
        ->toBeTrue();
});

test('restricted companies deny access even when direct and global roles are combined', function () {
    $company = User::findOrFail(1)->companies()->firstOrFail();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id, ['include_global_roles' => true]);

    app(UserCompanyAccessService::class)->syncUserAccess($user, ['manager'], [$company->id]);
    $roleName = BouncerFacade::scope()->onceTo($company->id, function (): string {
        $role = Role::query()->create(['name' => 'invoice-viewer', 'title' => 'Invoice viewer']);
        BouncerFacade::allow($role)->to('view-invoice', Invoice::class);

        return $role->name;
    });
    app(UserCompanyAccessService::class)->replaceCompanyRoles($user, $company->id, [$roleName]);
    Sanctum::actingAs($user);

    $this->withHeader('company', $company->id)
        ->getJson('/api/v1/bootstrap')
        ->assertForbidden();
});
