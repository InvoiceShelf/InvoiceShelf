<?php

namespace App\Services\Backup;

use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\FileDiskService;
use Spatie\Backup\BackupDestination\BackupDestination;

class BackupService
{
    public function __construct(
        private readonly FileDiskService $fileDiskService,
    ) {}

    /**
     * Resolve the backup FileDisk from the given ID, settings, or default.
     */
    public function resolveBackupDisk(?int $fileDiskId = null): ?FileDisk
    {
        if ($fileDiskId) {
            $disk = FileDisk::find($fileDiskId);
            if ($disk) {
                return $disk;
            }
        }

        $backupDiskId = Setting::getSetting('backup_disk_id');
        if ($backupDiskId) {
            $disk = FileDisk::find($backupDiskId);
            if ($disk) {
                return $disk;
            }
        }

        return FileDisk::where('set_as_default', true)->first();
    }

    /**
     * Create a BackupDestination pointing at the resolved disk.
     */
    public function getDestination(?int $fileDiskId = null): BackupDestination
    {
        $disk = $this->resolveBackupDisk($fileDiskId);

        $diskName = $disk
            ? $this->fileDiskService->registerDisk($disk)
            : config('filesystems.default');

        return BackupDestination::create($diskName, config('backup.backup.name'));
    }
}
