<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade data for v3.0.
     */
    public function up(): void
    {
        $this->migrateMediaDiskReferences();
    }

    /**
     * v3.0 replaced the temp_{driver} disk naming with disk_{id}.
     * Update existing media records so they reference valid disk names.
     */
    private function migrateMediaDiskReferences(): void
    {
        // temp_local was the dynamic name for the default local FileDisk.
        // In v3.0, system local disks use the 'local' config entry directly.
        DB::table('media')
            ->where('disk', 'temp_local')
            ->update(['disk' => 'local']);

        // temp_s3, temp_dropbox, etc. for remote disks — map to disk_{id}
        $remotePrefixes = ['temp_s3', 'temp_dropbox', 'temp_doSpaces', 'temp_s3compat'];

        foreach ($remotePrefixes as $oldDiskName) {
            $driver = str_replace('temp_', '', $oldDiskName);

            $fileDisk = DB::table('file_disks')
                ->where('driver', $driver)
                ->first();

            if ($fileDisk) {
                DB::table('media')
                    ->where('disk', $oldDiskName)
                    ->update(['disk' => 'disk_'.$fileDisk->id]);
            }
        }
    }

    public function down(): void
    {
        // Reverse: map disk_{id} back to temp_{driver}
        $fileDiskIds = DB::table('file_disks')
            ->whereNotIn('type', ['SYSTEM'])
            ->get();

        foreach ($fileDiskIds as $disk) {
            DB::table('media')
                ->where('disk', 'disk_'.$disk->id)
                ->update(['disk' => 'temp_'.$disk->driver]);
        }

        DB::table('media')
            ->where('disk', 'local')
            ->update(['disk' => 'temp_local']);
    }
};
