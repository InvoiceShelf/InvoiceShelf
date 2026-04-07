<?php

namespace App\Services\Backup;

use App\Models\FileDisk;
use App\Services\FileDiskService;
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

        $diskName = app(FileDiskService::class)->registerDisk($fileDisk);

        config(['backup.backup.destination.disks' => [$diskName]]);

        return Config::fromArray(config('backup'));
    }
}
