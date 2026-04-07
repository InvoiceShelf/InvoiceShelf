<?php

namespace App\Console\Commands;

use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\FileDiskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateMediaToPrivateDisk extends Command
{
    protected $signature = 'media:secure {--dry-run : Show what would be moved without moving}';

    protected $description = 'Move sensitive media files (receipts) from the public disk to the private disk';

    public function handle(): int
    {
        $targetDisk = $this->resolveTargetDisk();

        if (! $targetDisk) {
            $this->error('No target disk found. Set a default FileDisk or configure media_disk_id setting.');

            return self::FAILURE;
        }

        $targetDiskName = app(FileDiskService::class)->registerDisk($targetDisk);
        $targetRoot = config('filesystems.disks.'.$targetDiskName.'.root');

        if (! $targetRoot) {
            $this->error('Could not resolve target disk root path.');

            return self::FAILURE;
        }

        $records = DB::table('media')
            ->where('disk', 'public')
            ->where(function ($query) {
                $query->where('collection_name', 'receipts');
            })
            ->get();

        if ($records->isEmpty()) {
            $this->info('No media files to migrate.');

            return self::SUCCESS;
        }

        $this->info('Found '.$records->count().' file(s) to migrate.');

        if ($this->option('dry-run')) {
            foreach ($records as $record) {
                $this->line("  Would move: media/{$record->id}/{$record->file_name}");
            }

            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;
        $publicMediaRoot = public_path('media');

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $record) {
            $relativePath = $record->id.DIRECTORY_SEPARATOR.$record->file_name;
            $sourcePath = $publicMediaRoot.DIRECTORY_SEPARATOR.$relativePath;
            $destPath = $targetRoot.DIRECTORY_SEPARATOR.$relativePath;

            if (! file_exists($sourcePath)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $destDir = dirname($destPath);
            if (! File::isDirectory($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            File::move($sourcePath, $destPath);

            DB::table('media')
                ->where('id', $record->id)
                ->update(['disk' => $targetDiskName]);

            $moved++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done. Moved: {$moved}, Skipped (missing): {$skipped}");

        // Clean up empty directories in public/media
        $this->cleanEmptyDirectories($publicMediaRoot);

        return self::SUCCESS;
    }

    private function resolveTargetDisk(): ?FileDisk
    {
        $mediaDiskId = Setting::getSetting('media_disk_id');

        if ($mediaDiskId) {
            return FileDisk::find($mediaDiskId);
        }

        return FileDisk::where('set_as_default', true)->first();
    }

    private function cleanEmptyDirectories(string $path): void
    {
        if (! File::isDirectory($path)) {
            return;
        }

        $directories = File::directories($path);

        foreach ($directories as $dir) {
            $this->cleanEmptyDirectories($dir);

            if (count(File::allFiles($dir)) === 0 && count(File::directories($dir)) === 0) {
                File::deleteDirectory($dir);
            }
        }
    }
}
