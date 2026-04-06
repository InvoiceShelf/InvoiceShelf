<?php

use App\Jobs\CreateBackupJob;
use App\Models\FileDisk;
use App\Models\User;
use App\Services\Backup\BackupConfigurationFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Backup\Config\Config;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Sanctum::actingAs(
        User::findOrFail(1),
        ['*']
    );
});

test('create backup does not require company header', function () {
    Queue::fake();

    $disk = FileDisk::factory()->create();

    postJson('/api/v1/backups', [
        'option' => 'full',
        'file_disk_id' => $disk->id,
    ])->assertOk()->assertJson([
        'success' => true,
    ]);

    Queue::assertPushed(CreateBackupJob::class);
});

test('backup configuration factory does not require company context', function () {
    $disk = FileDisk::factory()->create();

    $config = BackupConfigurationFactory::make([
        'option' => 'full',
        'file_disk_id' => $disk->id,
    ]);

    expect($config)->toBeInstanceOf(Config::class);
});
