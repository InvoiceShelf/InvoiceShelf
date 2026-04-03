<?php

use App\Http\Controllers\Company\Members\MembersController;
use App\Http\Requests\MemberRequest;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

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

test('get member', function () {
    $user = User::factory()->create();

    getJson("/api/v1/members/{$user->id}")->assertOk();
});

test('update member using a form request', function () {
    $this->assertActionUsesFormRequest(
        MembersController::class,
        'update',
        MemberRequest::class
    );
});
