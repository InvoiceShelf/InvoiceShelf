<?php

namespace App\Space;

use App\Models\CompanySetting;
use App\Models\FileDisk;
use Exception;
use Spatie\Backup\Config\Config;

class BackupConfigurationFactory
{
    public static function make($data = []): Config
    {
        if (blank($data['company'] ?? null)) {
            throw new Exception('The Company ID is missig');
        }

        if (blank($data['file_disk_id'] ?? null)) {
            throw new Exception('No file disk selected');
        }

        $fileDisk = FileDisk::find($data['file_disk_id']);

        $fileDisk->setConfig();

        $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');

        config(['backup.backup.destination.disks' => [$prefix.$fileDisk->driver]]);

        $companyNotificationEmail = CompanySetting::getSetting('notification_email', $data['company']);

        if ($companyNotificationEmail) {
            config(['backup.notifications.mail.to' => $companyNotificationEmail]);
        }

        $config = Config::fromArray(config('backup'));

        return $config;
    }
}
