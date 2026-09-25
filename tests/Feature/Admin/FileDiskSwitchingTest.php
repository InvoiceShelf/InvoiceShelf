<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('no global middleware lets a request pick the storage disk', function () {
    $global = app(Kernel::class)->getGlobalMiddleware();

    expect($global)->not->toContain('App\Platform\Storage\Http\Middleware\ConfigMiddleware');
});

test('the backup list still reads from the disk it names', function () {
    $user = User::find(1);
    Sanctum::actingAs($user, ['*']);
    $disk = FileDisk::factory()->create();

    getJson("/api/v1/backups?file_disk_id={$disk->id}", [
        'company' => $user->companies()->first()->id,
    ])->assertOk()
        ->assertJsonStructure(['backups'])
        ->assertJsonMissing(['error' => 'invalid_disk_credentials']);
});
