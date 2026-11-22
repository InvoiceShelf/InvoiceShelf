<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Storage\Jobs\CreateBackupJob;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Backup\Exceptions\BackupFailed;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

use function Pest\Laravel\postJson;

/**
 * A backup is backed up files, not an email about them. These run real
 * files-only backups of a small scratch directory onto a local disk in a
 * scratch directory, so what they check is whether the zip landed.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::findOrFail(1);
    Sanctum::actingAs($this->user, ['*']);

    $this->scratch = storage_path('framework/testing/backups-'.uniqid());
    File::ensureDirectoryExists($this->scratch.'/source');
    File::put($this->scratch.'/source/notes.txt', 'something worth keeping');

    config([
        'backup.backup.source.files.include' => [$this->scratch.'/source'],
        'backup.backup.source.files.exclude' => [],
    ]);

    $this->disk = FileDisk::factory()->create([
        'credentials' => ['driver' => 'local', 'root' => $this->scratch.'/disk'],
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->scratch);
});

function writtenBackups(string $scratch): array
{
    return File::isDirectory($scratch.'/disk') ? File::allFiles($scratch.'/disk') : [];
}

function mailServerIsDown(): void
{
    Mail::extend('down', fn () => new class implements TransportInterface
    {
        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            throw new RuntimeException('Connection to "process /usr/sbin/sendmail -bs -i" has been closed unexpectedly.');
        }

        public function __toString(): string
        {
            return 'down';
        }
    });

    config([
        'mail.mailers.down' => ['transport' => 'down'],
        'mail.default' => 'down',
    ]);
}

test('a mail server that is down does not fail a backup that was written', function () {
    mailServerIsDown();

    postJson('/api/v1/backups', [
        'option' => 'only-files',
        'file_disk_id' => $this->disk->id,
    ])->assertOk()->assertJson(['success' => true]);

    expect(writtenBackups($this->scratch))->toHaveCount(1);
});

test('a backup sends no email of its own', function () {
    Mail::fake();

    postJson('/api/v1/backups', [
        'option' => 'only-files',
        'file_disk_id' => $this->disk->id,
    ])->assertOk();

    expect(writtenBackups($this->scratch))->toHaveCount(1);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('a backup that fails still fails, and sends no email of its own', function () {
    Mail::fake();
    config(['backup.backup.source.files.include' => [$this->scratch.'/nothing-here']]);

    $job = new CreateBackupJob([
        'option' => 'only-files',
        'file_disk_id' => $this->disk->id,
    ]);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(BackupFailed::class);

    Mail::assertNothingSent();
    expect(writtenBackups($this->scratch))->toBeEmpty();
});
