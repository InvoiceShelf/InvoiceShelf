<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateBackupJob;
use App\Rules\Backup\PathToZip;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\Helpers\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupsController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        try {
            $destination = $this->backupService->getDestination($request->file_disk_id);

            $backups = $destination
                ->backups()
                ->map(function (Backup $backup) {
                    return [
                        'path' => $backup->path(),
                        'created_at' => $backup->date()->format('Y-m-d H:i:s'),
                        'size' => Format::humanReadableSize($backup->sizeInBytes()),
                    ];
                })
                ->toArray();

            return response()->json([
                'backups' => $backups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'backups' => [],
                'error' => 'invalid_disk_credentials',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $data = $request->all();

        dispatch(new CreateBackupJob($data))->onQueue(config('backup.queue.name'));

        return response()->json(['success' => true]);
    }

    public function destroy($disk, Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $validated = $request->validate([
            'path' => ['required', new PathToZip],
        ]);

        $destination = $this->backupService->getDestination($request->file_disk_id);

        $destination
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

        $destination = $this->backupService->getDestination($request->file_disk_id);

        $backup = $destination->backups()->first(function (Backup $backup) use ($validated) {
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
