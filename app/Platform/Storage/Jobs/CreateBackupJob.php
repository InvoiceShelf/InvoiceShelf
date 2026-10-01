<?php

namespace App\Platform\Storage\Jobs;

use App\Platform\Storage\Application\BackupConfigurationFactory;
use App\Platform\Storage\Application\BackupNotifier;
use App\Platform\Storage\Models\FileDisk;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Throwable;

/**
 * Runs one backup against the disk named in the request payload.
 *
 * With `notify` set, whoever started it (`user_id`) is emailed once the run
 * is over: after the zip is on the disk, or after it could not be written.
 */
class CreateBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Assemble the backup task, narrow it to the requested option, and run it.
     */
    public function handle(BackupNotifier $notifier): void
    {
        $job = BackupJobFactory::createFromConfig(
            BackupConfigurationFactory::make($this->data)
        );

        if (! defined('SIGINT')) {
            $job->disableSignals();
        }

        $option = (string) ($this->data['option'] ?? '');

        if ($option === 'only-db') {
            $job->dontBackupFilesystem();
        }

        if ($option === 'only-files') {
            $job->dontBackupDatabases();
        }

        $filename = empty($option)
            ? Carbon::now()->format(BackupJob::FILENAME_FORMAT)
            : str_replace('_', '-', $option).'-'.date('Y-m-d-H-i-s').'.zip';

        $job->setFilename($filename);

        $notify = (bool) ($this->data['notify'] ?? false);
        $userId = isset($this->data['user_id']) ? (int) $this->data['user_id'] : null;

        try {
            $job->run();
        } catch (Throwable $failure) {
            if ($notify) {
                $notifier->failed($userId, $option, $failure->getMessage());
            }

            throw $failure;
        }

        if ($notify) {
            $notifier->completed(
                $userId,
                $option,
                config('backup.backup.destination.filename_prefix').$filename,
                (string) FileDisk::query()->find($this->data['file_disk_id'])?->name,
            );
        }
    }
}
