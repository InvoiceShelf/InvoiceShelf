<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
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

test('creates a user without a company, and a super administrator', function () {
    postJson('api/v1/super-admin/users', newUserPayload())->assertCreated();
    expect(User::query()->where('email', 'sam.staff@example.com')->firstOrFail()->companies()->count())->toBe(0);

    postJson('api/v1/super-admin/users', newUserPayload(['email' => 'boss@example.com', 'is_super_admin' => true]))
        ->assertCreated();

    expect(User::query()->where('email', 'boss@example.com')->firstOrFail()->isSuperAdmin())->toBeTrue();
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
    $user->companies()->attach($this->company->id);
    BouncerFacade::scope()->onceTo($this->company->id, fn () => $user->assign('preset:manager'));

    putJson("api/v1/super-admin/users/{$user->id}", ['name' => 'Renamed', 'email' => $user->email])->assertOk();

    expect($user->fresh()->name)->toBe('Renamed')
        ->and(rolesIn($user, $this->company))->toBe(['preset:manager']);
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
