<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Sanctum::actingAs(User::find(1), ['*']);
});

it('allows super admins to validate marketplace tokens without a company header in admin mode', function () {
    getJson('/api/v1/modules/check?api_token=test-marketplace-token')
        ->assertOk()
        ->assertJson([
            'error' => 'invalid_token',
        ]);
});
