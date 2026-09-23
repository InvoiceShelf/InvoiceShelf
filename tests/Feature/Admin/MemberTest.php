<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Http\Controllers\Company\MembersController;
use App\Domains\Accounts\Http\Requests\MemberRequest;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('list members', function () {
    getJson('/api/v1/members')->assertOk();
});

test('store member using a form request', function () {
    $this->assertActionUsesFormRequest(
        MembersController::class,
        'store',
        MemberRequest::class
    );
});

test('get member belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    getJson("/api/v1/members/{$user->id}")->assertOk();
});

test('cannot view a member belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    getJson("/api/v1/members/{$user->id}")->assertForbidden();
});

test('cannot update a member belonging to another company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    putJson("/api/v1/members/{$user->id}", [
        'name' => 'Hacked',
        'email' => 'pwned@attacker.test',
        'companies' => [['id' => $companyId, 'role' => 'super admin']],
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'pwned@attacker.test']);
});

test('update member using a form request', function () {
    $this->assertActionUsesFormRequest(
        MembersController::class,
        'update',
        MemberRequest::class
    );
});

test('deletes a member belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    postJson('/api/v1/members/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('cannot bulk delete a member belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    postJson('/api/v1/members/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

/**
 * A company with the owner role every company is set up with, owned by someone
 * other than the acting user.
 */
function foreignCompany(): Company
{
    $company = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    app(CompanyService::class)->setupDefaults($company);

    return $company;
}

function memberPayload(array $companies, array $overrides = []): array
{
    return [
        'name' => 'New Member',
        'email' => 'new.member@example.com',
        'password' => 'long-enough-password',
        'companies' => $companies,
        ...$overrides,
    ];
}

test('a member cannot be filed into a company the caller does not own', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();

    postJson('/api/v1/members', memberPayload([
        ['id' => $own->id, 'role' => 'owner'],
        ['id' => $foreign->id, 'role' => 'owner'],
    ]))->assertUnprocessable();

    expect(User::where('email', 'new.member@example.com')->exists())->toBeFalse();
});

test('a member is filed into the caller\'s own company with one of its roles', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();

    postJson('/api/v1/members', memberPayload([['id' => $own->id, 'role' => 'owner']]))->assertCreated();

    expect(User::where('email', 'new.member@example.com')->firstOrFail()->hasCompany($own->id))->toBeTrue();
});

test('a role must exist in the company it is granted in', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();

    postJson('/api/v1/members', memberPayload([['id' => $own->id, 'role' => 'made-up-role']]))->assertUnprocessable();
});

test('an edit cannot move a member into a company the caller does not own', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($own->id);

    putJson("/api/v1/members/{$member->id}", memberPayload([
        ['id' => $own->id, 'role' => 'owner'],
        ['id' => $foreign->id, 'role' => 'owner'],
    ], ['email' => $member->email, 'password' => null]))->assertUnprocessable();

    expect($member->fresh()->hasCompany($foreign->id))->toBeFalse();
});

test('an edit leaves a member\'s other companies alone', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach([$own->id, $foreign->id]);

    putJson("/api/v1/members/{$member->id}", memberPayload(
        [['id' => $own->id, 'role' => 'owner']],
        ['name' => 'Renamed', 'email' => $member->email, 'password' => null],
    ))->assertOk();

    expect($member->fresh()->name)->toBe('Renamed')
        ->and($member->fresh()->hasCompany($foreign->id))->toBeTrue();
});

test('the credentials of a member who belongs elsewhere too cannot be changed', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $member = User::factory()->create(['role' => 'user', 'email' => 'shared@example.com']);
    $member->companies()->attach([$own->id, $foreign->id]);
    $password = $member->password;

    putJson("/api/v1/members/{$member->id}", memberPayload(
        [['id' => $own->id, 'role' => 'owner']],
        ['email' => 'shared@example.com', 'password' => 'taken-over-password'],
    ))->assertUnprocessable();

    putJson("/api/v1/members/{$member->id}", memberPayload(
        [['id' => $own->id, 'role' => 'owner']],
        ['email' => 'taken@example.com', 'password' => null],
    ))->assertUnprocessable();

    expect($member->fresh()->password)->toBe($password)
        ->and($member->fresh()->email)->toBe('shared@example.com');
});
