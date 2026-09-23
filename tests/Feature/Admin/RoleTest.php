<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

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

test('create a member with one of the company\'s roles', function () {
    $data = [
        'email' => 'loremipsum@gmail.com',
        'name' => 'lorem',
        'password' => 'lorem@123',
    ];
    // The role has to exist in the company: an unknown name is refused rather
    // than created on the fly.
    $data['companies'] = [
        [
            'role' => 'owner',
            'id' => 1,
        ],
    ];

    postJson('api/v1/members', $data)
        ->assertStatus(201);

    $data = collect($data)
        ->only([
            'email',
            'name',
        ])
        ->toArray();

    $this->assertDatabaseHas('users', $data);
});
