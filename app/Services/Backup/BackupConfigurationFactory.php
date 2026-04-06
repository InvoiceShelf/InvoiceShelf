<?php

namespace App\Services\Backup;

use App\Models\FileDisk;
use Exception;
use Spatie\Backup\Config\Config;

class BackupConfigurationFactory
{
    public static function make(array $data = []): Config
    {
        if (blank($data['file_disk_id'] ?? null)) {
            throw new Exception('No file disk selected');
        }

        $fileDisk = FileDisk::find($data['file_disk_id']);

        $fileDisk->setConfig();

        $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');

        config(['backup.backup.destination.disks' => [$prefix.$fileDisk->driver]]);

        $config = Config::fromArray(config('backup'));

        return $config;
    }
}
