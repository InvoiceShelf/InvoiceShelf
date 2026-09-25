<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Storage\Application\FileDiskService;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

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

test('get file disks', function () {
    $response = getJson('/api/v1/disks');

    $response->assertOk();
});

test('create file disk', function () {
    $disk = FileDisk::factory()->raw();

    $response = postJson('/api/v1/disks', $disk);

    $disk['credentials'] = json_encode($disk['credentials']);
    $this->assertDatabaseHas('file_disks', $disk);
});

test('update file disk', function () {
    $disk = FileDisk::factory()->create();

    $disk2 = FileDisk::factory()->raw();

    $response = putJson("/api/v1/disks/{$disk->id}", $disk2)->assertStatus(200);

    $disk2['credentials'] = json_encode($disk2['credentials']);

    $this->assertDatabaseHas('file_disks', $disk2);
});

test('get disk', function () {
    $disk = FileDisk::factory()->create();

    $response = getJson("/api/v1/disks/{$disk->driver}");

    $response->assertStatus(200);
});

test('get drivers', function () {
    $response = getJson('/api/v1/disk/drivers');

    $response->assertStatus(200);
});

test('a disk cannot borrow another driver through its credentials', function () {
    $disk = FileDisk::factory()->create([
        'driver' => 's3',
        'credentials' => [
            'driver' => 'local',
            'root' => '/',
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'eu-central-1',
            'bucket' => 'bucket',
        ],
    ]);

    $name = app(FileDiskService::class)->registerDisk($disk);

    expect(config("filesystems.disks.{$name}.driver"))->toBe('s3');
});

test('a stored disk with a driver outside the allowlist falls back to the local disk', function () {
    $disk = FileDisk::factory()->create([
        'driver' => 'views',
        'credentials' => ['root' => '/tmp'],
    ]);

    expect(app(FileDiskService::class)->registerDisk($disk))->toBe('local');
});

test('creating a disk with a driver outside the allowlist is refused', function () {
    postJson('/api/v1/disks', [
        'name' => 'templates',
        'driver' => 'views',
        'credentials' => ['root' => '/tmp'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['driver']);
});

test('an s3 compatible endpoint on a private address is refused', function () {
    postJson('/api/v1/disks', [
        'name' => 'minio',
        'driver' => 's3compat',
        'credentials' => [
            'endpoint' => 'http://127.0.0.1:9000',
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'bucket',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['credentials.endpoint']);
});

test('updating a disk is validated like creating one', function () {
    $disk = FileDisk::factory()->create();

    putJson("/api/v1/disks/{$disk->id}", [
        'name' => 'templates',
        'driver' => 'views',
        'credentials' => ['root' => '/tmp'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['driver']);
});

test('moving the default flag alone needs no credentials', function () {
    $disk = FileDisk::factory()->create();

    putJson("/api/v1/disks/{$disk->id}", ['set_as_default' => true])->assertOk();

    expect($disk->fresh()->set_as_default)->toBeTrue();
});
