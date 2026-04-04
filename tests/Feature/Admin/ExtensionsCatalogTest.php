<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

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

test('modules list returns extensions from public catalog', function () {
    Http::fake([
        config('invoiceshelf.extensions.manifest_url') => Http::response([
            'extensions' => [
                [
                    'slug' => 'test-ext',
                    'module_name' => 'TestExt',
                    'name' => 'Test Extension',
                    'description' => 'Desc',
                    'version' => '1.0.0',
                    'author' => 'Tester',
                    'download_url' => 'https://example.com/z.zip',
                    'repository' => 'https://github.com/foo/bar',
                ],
            ],
        ], 200),
    ]);

    $response = getJson('/api/v1/modules');

    $response->assertOk();
    $response->assertJsonPath('data.0.slug', 'test-ext');
    $response->assertJsonPath('data.0.module_name', 'TestExt');
});
