<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\RolePreset;
use App\Domains\Accounts\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * Administration → Users: the super administrator creates accounts and sets
 * which companies they belong to, with a role in each.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->admin = User::query()->where('role', 'super admin')->firstOrFail();
    $this->company = $this->admin->companies()->firstOrFail();
    $this->other = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    app(CompanyService::class)->setupDefaults($this->other);

    Sanctum::actingAs($this->admin, ['*']);
});

/**
 * The names of the roles a user holds in one company.
 *
 * @return list<string>
 */
function rolesIn(User $user, Company $company): array
{
    return BouncerFacade::scope()->onceTo($company->id, fn () => $user->fresh()->getRoles()->values()->all());
}

function newUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Sam Staff',
        'email' => 'sam.staff@example.com',
        'password' => 'long-enough-password',
        'companies' => [],
        ...$overrides,
    ];
}

test('creates a user in two companies with a role in each', function () {
    postJson('api/v1/super-admin/users', newUserPayload(['companies' => [
        ['id' => $this->company->id, 'role' => 'preset:read-only'],
        ['id' => $this->other->id, 'role' => 'owner'],
    ]]))->assertCreated()->assertJsonCount(2, 'data.companies');

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();

    expect(Hash::check('long-enough-password', $user->password))->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and(rolesIn($user, $this->company))->toBe(['preset:read-only'])
        ->and(rolesIn($user, $this->other))->toBe(['owner']);
});

test('creates and updates a user with multiple roles in one company', function () {
    postJson('api/v1/super-admin/users', newUserPayload(['companies' => [[
        'id' => $this->company->id,
        'roles' => ['preset:manager', 'preset:read-only'],
        'include_global_roles' => true,
    ]]]))->assertCreated();

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();

    expect(rolesIn($user, $this->company))->toBe(['preset:manager', 'preset:read-only'])
        ->and($user->companies()->where('companies.id', $this->company->id)->firstOrFail()->pivot->include_global_roles)->toBe(1);

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'companies' => [[
            'id' => $this->company->id,
            'roles' => ['preset:manager'],
            'include_global_roles' => false,
        ]],
    ])->assertOk();

    expect(DB::table('assigned_roles')
        ->join('roles', 'roles.id', '=', 'assigned_roles.role_id')
        ->where('assigned_roles.entity_id', $user->id)
        ->where('assigned_roles.entity_type', $user->getMorphClass())
        ->where('assigned_roles.scope', $this->company->id)
        ->pluck('roles.name')
        ->all())->toBe(['preset:manager']);

    expect(rolesIn($user, $this->company))->toBe(['preset:manager'])
        ->and($user->fresh()->companies()->where('companies.id', $this->company->id)->firstOrFail()->pivot->include_global_roles)->toBe(0);
});

test('legacy one-role membership payloads default global role combination off', function () {
    postJson('api/v1/super-admin/users', newUserPayload(['companies' => [[
        'id' => $this->company->id,
        'role' => 'preset:read-only',
    ]]]))->assertCreated()
        ->assertJsonPath('data.companies.0.include_global_roles', false);

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();

    expect($user->companies()->where('companies.id', $this->company->id)->firstOrFail()->pivot->include_global_roles)->toBe(0);
});

test('lists readable assigned role titles in administration users', function () {
    postJson('api/v1/super-admin/users', newUserPayload([
        'companies' => [[
            'id' => $this->company->id,
            'roles' => ['preset:manager', 'preset:read-only'],
        ]],
        'global_roles' => ['read-only'],
    ]))->assertCreated();

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();
    $listed = collect(getJson('api/v1/super-admin/users?limit=100')->assertOk()->json('data'))
        ->firstWhere('id', $user->id);

    expect($listed['role'])->toBe('user')
        ->and($listed['role_labels'])->toEqualCanonicalizing([
            'Manager',
            'Read only',
        ]);
});

test('creates a user without a company, and a super administrator', function () {
    postJson('api/v1/super-admin/users', newUserPayload())->assertCreated();
    expect(User::query()->where('email', 'sam.staff@example.com')->firstOrFail()->companies()->count())->toBe(0);

    postJson('api/v1/super-admin/users', newUserPayload(['email' => 'boss@example.com', 'is_super_admin' => true]))
        ->assertCreated();

    expect(User::query()->where('email', 'boss@example.com')->firstOrFail()->isSuperAdmin())->toBeTrue();
});

test('creating a super administrator ignores access assignments', function () {
    postJson('api/v1/super-admin/users', newUserPayload([
        'email' => 'boss@example.com',
        'is_super_admin' => true,
        'companies' => [['id' => $this->company->id, 'roles' => ['preset:manager']]],
        'global_roles' => ['manager'],
        'restricted_company_ids' => [$this->company->id],
    ]))->assertCreated();

    $user = User::query()->where('email', 'boss@example.com')->firstOrFail();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->companies()->exists())->toBeFalse()
        ->and($user->globalRolePresets()->exists())->toBeFalse()
        ->and($user->restrictedCompanies()->exists())->toBeFalse();
});

test('creates a user with global roles and restricted companies', function () {
    postJson('api/v1/super-admin/users', newUserPayload([
        'global_roles' => ['read-only'],
        'restricted_company_ids' => [$this->other->id],
    ]))->assertCreated()
        ->assertJsonPath('data.global_role_keys', ['read-only'])
        ->assertJsonPath('data.restricted_company_ids', [$this->other->id]);

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();

    expect($user->companies()->count())->toBe(0)
        ->and($user->globalRolePresets()->pluck('key')->all())->toBe(['read-only'])
        ->and($user->restrictedCompanies()->pluck('companies.id')->all())->toBe([$this->other->id]);

    $abilities = BouncerFacade::scope()->onceTo(
        $this->company->id,
        fn () => $user->fresh()->getAbilities()->pluck('name')->all(),
    );

    expect($abilities)->toContain('view-invoice')
        ->and($abilities)->not->toContain('create-invoice');
});

test('a newly created global Manager can see company navigation in an accessible company', function () {
    postJson('api/v1/super-admin/users', newUserPayload([
        'global_roles' => ['manager'],
        'restricted_company_ids' => [$this->other->id],
    ]))->assertCreated();

    $user = User::query()->where('email', 'sam.staff@example.com')->firstOrFail();

    Sanctum::actingAs($user, ['*']);
    app()->getProvider(AppServiceProvider::class)->addMenus();

    $response = $this->withHeader('company', $this->company->id)
        ->getJson('api/v1/bootstrap')
        ->assertOk();

    expect(collect($response->json('current_user_abilities'))->pluck('name')->all())
        ->toContain('dashboard', 'view-customer', 'view-invoice')
        ->and(collect($response->json('main_menu'))->pluck('name')->all())
        ->toContain('Dashboard', 'Customers', 'Invoices', 'Reports');
});

test('global roles grant company access except to restricted companies', function () {
    $user = User::factory()->create(['role' => 'user']);

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'global_roles' => ['manager'],
        'restricted_company_ids' => [$this->other->id],
    ])->assertOk();

    Sanctum::actingAs($user, ['*']);

    $response = $this->withHeader('company', $this->company->id)
        ->getJson('api/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('current_company.id', $this->company->id);

    expect(collect($response->json('companies'))->pluck('id')->all())
        ->toContain($this->company->id)
        ->not->toContain($this->other->id);

    $this->withHeader('company', $this->other->id)
        ->getJson('api/v1/bootstrap')
        ->assertForbidden();

    $fallback = $this->withHeader('company', 999999)
        ->getJson('api/v1/bootstrap')
        ->assertOk();

    expect($fallback->json('current_company.id'))
        ->not->toBe(999999)
        ->not->toBe($this->other->id);
});

test('a global Manager role grants its abilities in an accessible company', function () {
    $user = User::factory()->create(['role' => 'user']);

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'global_roles' => ['manager'],
    ])->assertOk();

    Sanctum::actingAs($user, ['*']);
    app()->getProvider(AppServiceProvider::class)->addMenus();

    $response = $this->withHeader('company', $this->company->id)
        ->getJson('api/v1/bootstrap')
        ->assertOk();

    expect(collect($response->json('current_user_abilities'))->pluck('name')->all())
        ->toContain('dashboard', 'view-customer', 'view-invoice')
        ->and(collect($response->json('main_menu'))->pluck('name')->all())
        ->toContain('Customers', 'Invoices');
});

test('a restricted company cannot also be directly assigned', function () {
    postJson('api/v1/super-admin/users', newUserPayload([
        'companies' => [['id' => $this->company->id, 'role' => 'preset:read-only']],
        'restricted_company_ids' => [$this->company->id],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('restricted_company_ids');

    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->company->id);

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'restricted_company_ids' => [$this->company->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('restricted_company_ids');

    $restricted = User::factory()->create(['role' => 'user']);
    $restricted->restrictedCompanies()->attach($this->company->id);

    putJson("api/v1/super-admin/users/{$restricted->id}", [
        'name' => $restricted->name,
        'email' => $restricted->email,
        'companies' => [['id' => $this->company->id, 'role' => 'preset:read-only']],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('restricted_company_ids');
});

test('refuses a role from another company and writes nothing', function () {
    $roleOfOther = Role::query()->withoutGlobalScopes()->where('scope', $this->other->id)->where('name', 'owner')->firstOrFail();
    BouncerFacade::scope()->onceTo($this->other->id, function () {
        BouncerFacade::allow('other-only')->to('view-invoice', null);
    });

    postJson('api/v1/super-admin/users', newUserPayload(['companies' => [['id' => $this->company->id, 'role' => 'other-only']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('companies.0.role');

    expect(User::query()->where('email', 'sam.staff@example.com')->exists())->toBeFalse()
        ->and(Role::query()->withoutGlobalScopes()->where('scope', $this->company->id)->where('name', 'other-only')->exists())->toBeFalse()
        ->and($roleOfOther->exists)->toBeTrue();
});

test('refuses a duplicate email and the same company twice', function () {
    postJson('api/v1/super-admin/users', newUserPayload(['email' => $this->admin->email]))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    postJson('api/v1/super-admin/users', newUserPayload(['companies' => [
        ['id' => $this->company->id, 'role' => 'owner'],
        ['id' => $this->company->id, 'role' => 'preset:manager'],
    ]]))->assertUnprocessable()->assertJsonValidationErrors('companies.1.id');
});

test('editing without companies leaves memberships alone', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->company->id, ['include_global_roles' => true]);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:manager'));

    putJson("api/v1/super-admin/users/{$user->id}", ['name' => 'Renamed', 'email' => $user->email])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed')
        ->and(rolesIn($user, $this->company))->toBe(['preset:manager'])
        ->and($user->fresh()->companies()->where('companies.id', $this->company->id)->firstOrFail()->pivot->include_global_roles)->toBe(1);
});

test('editing companies preserves global role combination when older clients omit it', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->company->id, ['include_global_roles' => true]);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:read-only'));

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'companies' => [['id' => $this->company->id, 'roles' => ['preset:read-only']]],
    ])->assertOk()
        ->assertJsonPath('data.companies.0.include_global_roles', true);

    expect($user->fresh()->companies()->where('companies.id', $this->company->id)->firstOrFail()->pivot->include_global_roles)->toBe(1)
        ->and(rolesIn($user, $this->company))->toBe(['preset:read-only']);
});

test('editing with companies replaces memberships and drops the role of a company left', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->company->id);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:manager'));

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'companies' => [['id' => $this->other->id, 'role' => 'preset:read-only']],
    ])->assertOk()->assertJsonCount(1, 'data.companies');

    expect($user->fresh()->companies()->pluck('companies.id')->all())->toBe([$this->other->id])
        ->and(rolesIn($user, $this->other))->toBe(['preset:read-only'])
        ->and(rolesIn($user, $this->company))->toBe([]);
});

test('an owner stays in the company they own, as its owner', function () {
    $owner = User::query()->findOrFail($this->other->owner_id);
    $owner->companies()->syncWithoutDetaching([$this->other->id]);
    BouncerFacade::scope()->onceTo($this->other->id, fn () => $owner->assign('owner'));

    putJson("api/v1/super-admin/users/{$owner->id}", ['name' => $owner->name, 'email' => $owner->email, 'companies' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('companies');

    putJson("api/v1/super-admin/users/{$owner->id}", [
        'name' => $owner->name,
        'email' => $owner->email,
        'companies' => [['id' => $this->other->id, 'role' => 'preset:read-only']],
    ])->assertUnprocessable()->assertJsonValidationErrors('companies');
});

test('the super administrator flag can be changed, but not on yourself', function () {
    $user = User::factory()->create(['role' => 'super admin']);

    putJson("api/v1/super-admin/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'is_super_admin' => false])
        ->assertOk();
    expect($user->fresh()->isSuperAdmin())->toBeFalse();

    putJson("api/v1/super-admin/users/{$this->admin->id}", ['name' => $this->admin->name, 'email' => $this->admin->email, 'is_super_admin' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('is_super_admin');
});

test('promoting a user to super administrator preserves existing access assignments', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->company->id, ['include_global_roles' => true]);
    $user->globalRolePresets()->sync(RolePreset::query()->where('key', 'manager')->pluck('id'));
    $user->restrictedCompanies()->attach($this->other->id);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:read-only'));

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => 'Promoted User',
        'email' => $user->email,
        'is_super_admin' => true,
        'companies' => [['id' => $this->other->id, 'roles' => ['preset:manager']]],
        'global_roles' => ['read-only'],
        'restricted_company_ids' => [$this->company->id],
    ])->assertOk();

    expect($user->fresh()->name)->toBe('Promoted User')
        ->and($user->fresh()->isSuperAdmin())->toBeTrue()
        ->and($user->fresh()->companies()->pluck('companies.id')->all())->toBe([$this->company->id])
        ->and(rolesIn($user, $this->company))->toBe(['preset:read-only'])
        ->and($user->fresh()->globalRolePresets()->pluck('key')->all())->toBe(['manager'])
        ->and($user->fresh()->restrictedCompanies()->pluck('companies.id')->all())->toBe([$this->other->id]);
});

test('updating a super administrator preserves existing access assignments', function () {
    $user = User::factory()->create(['role' => 'super admin']);
    $user->companies()->attach($this->company->id, ['include_global_roles' => true]);
    $user->globalRolePresets()->sync(RolePreset::query()->where('key', 'manager')->pluck('id'));
    $user->restrictedCompanies()->attach($this->other->id);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:read-only'));

    putJson("api/v1/super-admin/users/{$user->id}", [
        'name' => 'Renamed Administrator',
        'email' => $user->email,
        'companies' => [],
        'global_roles' => [],
        'restricted_company_ids' => [],
    ])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed Administrator')
        ->and($user->fresh()->companies()->pluck('companies.id')->all())->toBe([$this->company->id])
        ->and(rolesIn($user, $this->company))->toBe(['preset:read-only'])
        ->and($user->fresh()->globalRolePresets()->pluck('key')->all())->toBe(['manager'])
        ->and($user->fresh()->restrictedCompanies()->pluck('companies.id')->all())->toBe([$this->other->id]);
});

test('lists the roles of one company, Owner and presets first', function () {
    BouncerFacade::scope()->onceTo($this->company->id, fn () => BouncerFacade::allow('aardvark')->to('view-invoice', null));

    $names = collect(getJson("api/v1/super-admin/companies/{$this->company->id}/roles")->assertOk()->json('data'))->pluck('name')->all();

    expect($names[0])->toBe('owner')
        ->and(array_slice($names, 1, 2))->toBe(['preset:manager', 'preset:read-only'])
        ->and($names)->toContain('aardvark');
});

test('only the super administrator can create users or read company roles', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'user']), ['*']);

    postJson('api/v1/super-admin/users', newUserPayload())->assertForbidden();
    getJson("api/v1/super-admin/companies/{$this->company->id}/roles")->assertForbidden();
});
