<?php

namespace App\Platform\Storage\Http;

use App\Platform\Http\Controller;
use App\Platform\Storage\Application\BackupService;
use App\Platform\Storage\Jobs\CreateBackupJob;
use App\Platform\Storage\Rules\PathToZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\Helpers\Format;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The archives kept on a storage target.
 *
 * Which target is a per-request decision: `file_disk_id` selects one, and
 * without it the backup purpose setting decides, falling back to the default
 * disk. Every action re-resolves it, so a caller may list one disk and delete
 * from another in the same session.
 *
 * An archive is addressed by its path on that disk, and a path is only ever
 * accepted if it names a zip -- the one thing standing between these endpoints
 * and an arbitrary file on the storage target.
 */
class BackupsController extends Controller
{
    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    /**
     * What is currently stored on the selected disk, newest-first only insofar
     * as the destination hands them back that way.
     *
     * Anything that goes wrong while reading -- credentials the remote rejects,
     * a bucket that is not there, a network that is not either -- is answered
     * with 200 and an empty list carrying an error marker beside it, rather
     * than a failure status. The screen renders "no backups yet" plus the
     * driver's own words about why. Preserved: the SPA switches on the marker,
     * not on the status code.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        try {
            $target = $this->backupService->getDestination($request->file_disk_id);

            $archives = $target
                ->backups()
                ->map(function (Backup $archive) {
                    return [
                        'path' => $archive->path(),
                        'created_at' => $archive->date()->format('Y-m-d H:i:s'),
                        'size' => Format::humanReadableSize($archive->sizeInBytes()),
                    ];
                })
                ->toArray();

            return response()->json([
                'backups' => $archives,
            ]);
        } catch (\Exception $failure) {
            return response()->json([
                'backups' => [],
                'error' => 'invalid_disk_credentials',
                'error_message' => $failure->getMessage(),
            ]);
        }
    }

    /**
     * Queue an archive run and answer immediately.
     *
     * The whole body is handed to the job untouched -- `option` (everything,
     * database only, or files only) and the disk selection are read there, and
     * the reply says nothing about whether the run later succeeded. Nothing is
     * validated at this end, so an unrecognised option is a job-time problem,
     * not a request-time one.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $payload = $request->all();

        dispatch(new CreateBackupJob($payload))->onQueue(config('backup.queue.name'));

        return response()->json(['success' => true]);
    }

    /**
     * Remove one archive from the selected disk.
     *
     * The route carries a `{backup}` segment because the endpoint is registered
     * as part of a resource, but nothing reads it -- the archive is identified
     * by the `path` in the body, and the segment can be any value at all.
     *
     * KNOWN DEFECT: a path that names no archive on the disk leaves the search
     * empty and the delete is attempted on nothing, which is a 500 rather than
     * a 404. Reproduced as found.
     */
    public function destroy($disk, Request $request): JsonResponse
    {
        $this->authorize('manage backups');

        $path = $this->requireArchivePath($request);

        $target = $this->backupService->getDestination($request->file_disk_id);

        $target
            ->backups()
            ->first(function (Backup $archive) use ($path) {
                return $archive->path() === $path;
            })
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Stream one archive back as a download.
     *
     * Sent as a stream rather than a file response because the archive may live
     * on a remote disk and is never staged locally. The length is taken from
     * the destination's own metadata, so the browser gets a progress bar; the
     * no-cache headers keep an intermediary from holding on to a database dump.
     *
     * A path that names nothing is 422 with a bare sentence -- plain text, not
     * the JSON envelope the rest of the controller answers with. Preserved.
     */
    public function download(Request $request): Response|StreamedResponse
    {
        $this->authorize('manage backups');

        $path = $this->requireArchivePath($request);

        $target = $this->backupService->getDestination($request->file_disk_id);

        $archive = $target->backups()->first(function (Backup $candidate) use ($path) {
            return $candidate->path() === $path;
        });

        if (! $archive) {
            return response('Backup not found', 422);
        }

        $name = pathinfo($archive->path(), PATHINFO_BASENAME);

        return response()->stream(function () use ($archive) {
            $stream = $archive->stream();
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Type' => 'application/zip',
            'Content-Length' => $archive->sizeInBytes(),
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Pragma' => 'public',
        ]);
    }

    /**
     * The archive path both write endpoints work from: required, and a zip.
     */
    private function requireArchivePath(Request $request): string
    {
        return $request->validate(['path' => ['required', new PathToZip]])['path'];
    }
}
