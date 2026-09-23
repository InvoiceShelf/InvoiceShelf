<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\Database\Role;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('create super admin role', function () {
    $data = [
        'email' => 'loremipsum@gmail.com',
        'name' => 'lorem',
        'password' => 'lorem@123',
    ];
    $data['companies'] = [
        [
            'role' => 'super admin',
            'id' => 1,
        ],
    ];

    postJson('api/v1/users', $data)
        ->assertStatus(201);

    $data = collect($data)
        ->only([
            'email',
            'name',
        ])
        ->toArray();

    $this->assertDatabaseHas('users', $data);
});

test('a role of another company can be neither read, changed nor deleted', function () {
    $other = Company::factory()->create();
    $other->setupRoles();
    $foreign = Role::withoutGlobalScopes()->where('scope', $other->id)->firstOrFail();

    getJson("api/v1/roles/{$foreign->id}")->assertForbidden();

    expect(putJson("api/v1/roles/{$foreign->id}", ['name' => 'renamed', 'abilities' => []])->status())->toBeIn([403, 404])
        ->and(deleteJson("api/v1/roles/{$foreign->id}")->status())->toBeIn([403, 404])
        ->and(Role::withoutGlobalScopes()->find($foreign->id)?->name)->toBe($foreign->name);
});
