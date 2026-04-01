<?php

use App\Models\FileDisk;
use App\Models\User;
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

test('validate s3 compatible file disk credentials', function () {
    postJson('/api/v1/disks', [
        'name' => 'minio',
        'driver' => 's3compat',
        'credentials' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'credentials.endpoint',
        'credentials.key',
        'credentials.secret',
        'credentials.region',
        'credentials.bucket',
        'credentials.root',
    ]);
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

test('get s3 compatible disk config', function () {
    config([
        'filesystems.disks.s3compat.endpoint' => 'http://minio:9000',
        'filesystems.disks.s3compat.key' => 'invoiceshelf',
        'filesystems.disks.s3compat.secret' => 'invoiceshelf123',
        'filesystems.disks.s3compat.region' => 'us-east-1',
        'filesystems.disks.s3compat.bucket' => 'invoiceshelf-local',
        'filesystems.disks.s3compat.root' => 'generated-pdfs',
    ]);

    getJson('/api/v1/disks/s3compat')
        ->assertOk()
        ->assertJson([
            'endpoint' => 'http://minio:9000',
            'key' => 'invoiceshelf',
            'secret' => 'invoiceshelf123',
            'region' => 'us-east-1',
            'bucket' => 'invoiceshelf-local',
            'root' => 'generated-pdfs',
        ]);
});

test('get drivers', function () {
    $response = getJson('/api/v1/disk/drivers');

    $response->assertStatus(200);
});
