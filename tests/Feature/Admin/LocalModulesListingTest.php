<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

it('lists local modules even when catalog is unavailable', function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $modulesPath = base_path('Modules/TestLocal');

    File::deleteDirectory($modulesPath);
    File::makeDirectory($modulesPath, 0755, true);

    File::put($modulesPath.'/module.json', json_encode([
        'name' => 'TestLocal',
        'alias' => 'test-local',
        'description' => 'Local test module',
        'providers' => [],
    ], JSON_THROW_ON_ERROR));

    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $user = User::find(1);

    $this->withHeaders([
        'company' => (string) $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);

    $this
        ->getJson('/api/v1/modules')
        ->assertSuccessful()
        ->assertJsonPath('meta.catalog_unavailable', true)
        ->assertJsonFragment([
            'module_name' => 'TestLocal',
            'slug' => 'test-local',
        ]);

    File::deleteDirectory($modulesPath);
});
