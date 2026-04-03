<?php

namespace App\Http\Controllers\Admin\Backup;

use App\Http\Controllers\Controller;
use App\Jobs\CreateBackupJob;
use App\Models\FileDisk;
use App\Rules\Backup\PathToZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Helpers\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $configuredBackupDisks = config('backup.backup.destination.disks');

        try {
            if ($request->file_disk_id) {
                $fileDisk = FileDisk::find($request->file_disk_id);
                if ($fileDisk) {
                    $fileDisk->setConfig();
                    $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');
                    config(['backup.backup.destination.disks' => [$prefix.$fileDisk->driver]]);
                    $configuredBackupDisks = config('backup.backup.destination.disks');
                }
            }

            $backupDestination = BackupDestination::create(config('filesystems.default'), config('backup.backup.name'));

            $backups = Cache::remember("backups-{$request->file_disk_id}", now()->addSeconds(4), function () use ($backupDestination) {
                return $backupDestination
                    ->backups()
                    ->map(function (Backup $backup) {
                        return [
                            'path' => $backup->path(),
                            'created_at' => $backup->date()->format('Y-m-d H:i:s'),
                            'size' => Format::humanReadableSize($backup->sizeInBytes()),
                        ];
                    })
                    ->toArray();
            });

            return response()->json([
                'backups' => $backups,
                'disks' => $configuredBackupDisks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'backups' => [],
                'error' => 'invalid_disk_credentials',
                'error_message' => $e->getMessage(),
                'disks' => $configuredBackupDisks,
            ]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $data = $request->all();
        $data['company'] = $request->header('company');

        dispatch(new CreateBackupJob($data))->onQueue(config('backup.queue.name'));

        return response()->json(['success' => true]);
    }

    public function destroy($disk, Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $validated = $request->validate([
            'path' => ['required', new PathToZip],
        ]);

        $backupDestination = BackupDestination::create(config('filesystems.default'), config('backup.backup.name'));

        $backupDestination
            ->backups()
            ->first(function (Backup $backup) use ($validated) {
                return $backup->path() === $validated['path'];
            })
            ->delete();

        return response()->json(['success' => true]);
    }

    public function download(Request $request): Response|StreamedResponse
    {
        $this->authorize('manage backups');

        $validated = $request->validate([
            'path' => ['required', new PathToZip],
        ]);

        $backupDestination = BackupDestination::create(config('filesystems.default'), config('backup.backup.name'));

        $backup = $backupDestination->backups()->first(function (Backup $backup) use ($validated) {
            return $backup->path() === $validated['path'];
        });

        if (! $backup) {
            return response('Backup not found', 422);
        }

        $fileName = pathinfo($backup->path(), PATHINFO_BASENAME);

        return response()->stream(function () use ($backup) {
            $stream = $backup->stream();
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Type' => 'application/zip',
            'Content-Length' => $backup->sizeInBytes(),
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Pragma' => 'public',
        ]);
    }
}
