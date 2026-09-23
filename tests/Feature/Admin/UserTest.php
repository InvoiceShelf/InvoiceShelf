<?php

use App\Http\Controllers\V1\Admin\Users\UsersController;
use App\Http\Requests\UserRequest;
use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Faker\fake;
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

getJson('/api/v1/users')->assertOk();

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'store',
        UserRequest::class
    );
});

// test('store user', function () {
//     $data = [
//         'name' => fake()->name,
//         'email' => fake()->unique()->safeEmail,
//         'phone' => fake()->phoneNumber,
//         'password' => fake()->password
//     ];

//     postJson('/api/v1/users', $data)->assertOk();

//     $this->assertDatabaseHas('users', [
//         'name' => $data['name'],
//         'email' => $data['email'],
//         'phone' => $data['phone'],
//     ]);
// });

test('get user belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    getJson("/api/v1/users/{$user->id}")->assertOk();
});

test('cannot view a user belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    getJson("/api/v1/users/{$user->id}")->assertForbidden();
});

test('cannot update a user belonging to another company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    putJson("/api/v1/users/{$user->id}", [
        'name' => 'Hacked',
        'email' => 'pwned@attacker.test',
        'companies' => [['id' => $companyId, 'role' => 'super admin']],
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'pwned@attacker.test']);
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'update',
        UserRequest::class
    );
});

// test('update user', function () {
//     $user = User::factory()->create();

//     $data = [
//         'name' => fake()->name,
//         'email' => fake()->unique()->safeEmail,
//         'phone' => fake()->phoneNumber,
//         'password' => fake()->password
//     ];

//     putJson("/api/v1/users/{$user->id}", $data)->assertOk();

//     $this->assertDatabaseHas('users', [
//         'name' => $data['name'],
//         'email' => $data['email'],
//         'phone' => $data['phone'],
//     ]);
// });

test('deletes a user belonging to the current company', function () {
    $companyId = User::where('role', 'super admin')->first()->companies()->first()->id;
    $user = User::factory()->create();
    $user->companies()->attach($companyId);

    postJson('/api/v1/users/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('cannot bulk delete a user belonging to another company', function () {
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create()->id);

    postJson('/api/v1/users/delete', ['users' => [$user->id]])->assertOk();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

/**
 * A company with the owner role every company is set up with, owned by someone
 * other than the acting user.
 */
function foreignCompany(): Company
{
    $company = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    $company->setupRoles();

    return $company;
}

function userPayload(array $companies, array $overrides = []): array
{
    return [
        'name' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'long-enough-password',
        'companies' => $companies,
        ...$overrides,
    ];
}

test('a user cannot be filed into a company the caller does not own', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();

    postJson('/api/v1/users', userPayload([
        ['id' => $own->id, 'role' => 'super admin'],
        ['id' => $foreign->id, 'role' => 'super admin'],
    ]))->assertUnprocessable();

    expect(User::where('email', 'new.user@example.com')->exists())->toBeFalse();
});

test('a role must exist in the company it is granted in', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();

    postJson('/api/v1/users', userPayload([['id' => $own->id, 'role' => 'made-up-role']]))->assertUnprocessable();
});

test('an edit cannot move a user into a company the caller does not own', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($own->id);

    putJson("/api/v1/users/{$user->id}", userPayload([
        ['id' => $own->id, 'role' => 'super admin'],
        ['id' => $foreign->id, 'role' => 'super admin'],
    ], ['email' => $user->email, 'password' => null]))->assertUnprocessable();

    expect($user->fresh()->hasCompany($foreign->id))->toBeFalse();
});

test('an edit leaves a user\'s other companies alone', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach([$own->id, $foreign->id]);

    putJson("/api/v1/users/{$user->id}", userPayload(
        [['id' => $own->id, 'role' => 'super admin']],
        ['name' => 'Renamed', 'email' => $user->email, 'password' => null],
    ))->assertOk();

    expect($user->fresh()->name)->toBe('Renamed')
        ->and($user->fresh()->hasCompany($foreign->id))->toBeTrue();
});

test('the credentials of a user who belongs elsewhere too cannot be changed', function () {
    $own = User::where('role', 'super admin')->first()->companies()->first();
    $foreign = foreignCompany();
    $user = User::factory()->create(['role' => 'user', 'email' => 'shared@example.com']);
    $user->companies()->attach([$own->id, $foreign->id]);
    $password = $user->password;

    putJson("/api/v1/users/{$user->id}", userPayload(
        [['id' => $own->id, 'role' => 'super admin']],
        ['email' => 'shared@example.com', 'password' => 'taken-over-password'],
    ))->assertUnprocessable();

    expect($user->fresh()->password)->toBe($password);
});
