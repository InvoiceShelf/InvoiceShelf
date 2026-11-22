<?php

namespace App\Platform\Storage\Jobs;

use App\Platform\Storage\Application\BackupConfigurationFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

/**
 * Runs one backup against the disk named in the request payload.
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
     * Assemble the backup task, narrow it to the requested option and run it.
     */
    public function handle(): void
    {
        $job = BackupJobFactory::createFromConfig(
            BackupConfigurationFactory::make($this->data)
        );

        if (! defined('SIGINT')) {
            $job->disableSignals();
        }

        $option = $this->data['option'];

        if ($option === 'only-db') {
            $job->dontBackupFilesystem();
        }

        if ($option === 'only-files') {
            $job->dontBackupDatabases();
        }

        if (! empty($option)) {
            $job->setFilename(str_replace('_', '-', $option).'-'.date('Y-m-d-H-i-s').'.zip');
        }

        $job->run();
    }
}
