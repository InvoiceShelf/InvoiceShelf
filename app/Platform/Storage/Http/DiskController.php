<?php

namespace App\Platform\Storage\Http;

use App\Platform\Http\Controller;
use App\Platform\Operations\Models\Setting;
use App\Platform\Storage\Application\FileDiskService;
use App\Platform\Storage\Http\Requests\DiskEnvironmentRequest;
use App\Platform\Storage\Http\Requests\UpdateDiskRequest;
use App\Platform\Storage\Http\Resources\FileDiskResource;
use App\Platform\Storage\Models\FileDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * The registry of storage targets, and the three jobs they can be put to.
 *
 * A disk is a name, a driver, and a credential blob. Two of them are seeded and
 * marked as system disks -- the public and the private local trees -- and are
 * treated as furniture: their credentials are never revalidated and never
 * rewritten, and they cannot be deleted. Everything else is something an
 * operator added.
 *
 * Credentials are not taken on trust. Before a disk is written or rewritten the
 * blob is exercised against the driver for real -- a temporary disk is built
 * from it, a marker file written, read back and removed -- and a blob that
 * fails that round trip is refused with `invalid_credentials`. A custom
 * endpoint is checked for public reachability earlier still, in the form
 * request, so an endpoint aimed at the loopback interface or a metadata service
 * never reaches the point where the server would sign a request to it.
 *
 * Every endpoint here is gated on the same ability.
 */
class DiskController extends Controller
{
    public function __construct(
        private readonly FileDiskService $fileDiskService,
    ) {}

    /**
     * A page of registered disks, newest first.
     *
     * The raw input goes to the filter scope untouched, so `search` (matched
     * against both the name and the driver), the date range and the order pair
     * are all read there. A `limit` of "all" makes the pagination scope hand
     * back the whole collection instead of a paginator; with no `limit` a page
     * holds five.
     *
     * KNOWN DEFECT, same shape as the tax-type listing: asking for an order
     * without naming a field falls back to a `sequence_number` column this
     * table does not have. The safe-ordering helper drops the clause instead of
     * failing, so the request succeeds in whatever order the database chose.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage file disk');

        $perPage = $request->has('limit') ? $request->limit : 5;

        $page = FileDisk::applyFilters($request->all())
            ->latest()
            ->paginateData($perPage);

        return FileDiskResource::collection($page);
    }

    /**
     * Register a new disk, once its credentials have been proven to work.
     *
     * The live check runs before anything is written, so a disk row only ever
     * exists for credentials that succeeded at least once. Asking for the new
     * disk to be the default clears the flag everywhere else first -- exactly
     * one row carries it at any moment.
     *
     * The reply is 201 with the row as it was created, which means `type` reads
     * as null: the column's default is filled in by the database and the
     * in-memory model is not refreshed to see it.
     */
    public function store(DiskEnvironmentRequest $request): JsonResponse|FileDiskResource
    {
        $this->authorize('manage file disk');

        if (! $this->fileDiskService->validateCredentials($request->credentials, $request->driver)) {
            return respondJson('invalid_credentials', 'Invalid Credentials.');
        }

        $disk = $this->fileDiskService->create($request);

        return new FileDiskResource($disk);
    }

    /**
     * Rewrite a disk, or just hand it the default flag.
     *
     * Which of those happens is decided by what the body carries. Credentials
     * and a driver together mean a rewrite, and the new blob is proven live
     * first. Without both, the only thing this endpoint will do is move the
     * default flag -- and it does that only when the body asks for it.
     *
     * System disks never take the first branch whatever the body says: their
     * credentials point at the two built-in local trees and rewriting them
     * would strand every file already stored there. They fall through to the
     * flag, which is the one thing about them that is allowed to change.
     *
     * The payload is built from the model as it stands in memory. On the
     * set-default branch that is the saved state; on a no-op call it is simply
     * the row as loaded. A rewrite is validated like a registration before the
     * live check runs.
     */
    public function update(FileDisk $disk, UpdateDiskRequest $request): JsonResponse|FileDiskResource
    {
        $this->authorize('manage file disk');

        $credentials = $request->credentials;
        $driver = $request->driver;

        if ($credentials && $driver && ! $disk->isSystem()) {
            if (! $this->fileDiskService->validateCredentials($credentials, $driver)) {
                return respondJson('invalid_credentials', 'Invalid Credentials.');
            }

            $this->fileDiskService->update($disk, $request);
        } elseif ($request->set_as_default) {
            $this->fileDiskService->setAsDefault($disk);
        }

        return new FileDiskResource($disk);
    }

    /**
     * The empty credential form for a driver -- field names in the order the
     * edit screen should draw them, every value blank.
     *
     * The route segment is the driver name, not a disk id: nothing is loaded
     * and nothing existing is read. A driver nobody wrote a form for answers
     * with an empty JSON array rather than an empty object.
     *
     * KNOWN DEFECT: the S3-compatible arm has no break, so a request for it
     * falls through and is answered with the DigitalOcean Spaces form. The two
     * carry the same field names in a different order, so the screen still
     * works and the endpoint-first ordering written for the compatible driver
     * never reaches a client. Reproduced as found.
     */
    public function show($disk): JsonResponse
    {
        $this->authorize('manage file disk');

        $template = [];

        switch ($disk) {
            case 'local':
                // Relative to storage/app -- "backups" lands in
                // storage/app/backups once the disk is registered at runtime.
                $template = [
                    'root' => '',
                ];

                break;

            case 's3':
                $template = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'root' => '',
                ];

                break;

            case 's3compat':
                $template = [
                    'endpoint' => '',
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'root' => '',
                ];

                // Falls through -- see the note above.

            case 'doSpaces':
                $template = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'endpoint' => '',
                    'root' => '',
                ];

                break;

            case 'dropbox':
                $template = [
                    'token' => '',
                    'key' => '',
                    'secret' => '',
                    'app' => '',
                    'root' => '',
                ];

                break;
        }

        return response()->json(array_merge($template));
    }

    /**
     * Unregister a disk, if nothing depends on it.
     *
     * Three refusals, in order: the two seeded system disks are permanent; the
     * disk new uploads currently land on has to be replaced before it can go;
     * and a disk that still holds media would leave those files unreachable, so
     * the count is reported back and the operator is sent to migrate them
     * first.
     *
     * The media check looks under two names, because files were written under
     * the dynamic-prefix scheme in one era and under the bare driver name in
     * another. Note what that means: it counts by *driver*, not by disk, so two
     * local disks are indistinguishable here and files on either one block
     * deleting the other.
     */
    public function destroy(FileDisk $disk): JsonResponse
    {
        $this->authorize('manage file disk');

        if ($disk->isSystem()) {
            return respondJson('not_allowed', 'System disks cannot be deleted.');
        }

        // Reads the flag despite the name -- it is an accessor, not a setter.
        if ($disk->setAsDefault()) {
            return respondJson('not_allowed', 'The default disk cannot be deleted.');
        }

        $dynamicName = env('DYNAMIC_DISK_PREFIX', 'temp_').$disk->driver;

        $fileCount = DB::table('media')->where('disk', $dynamicName)->orWhere('disk', $disk->driver)->count();

        if ($fileCount > 0) {
            return respondJson('disk_has_files', 'Cannot delete this disk — it contains '.$fileCount.' file(s). Migrate files first.');
        }

        $disk->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * The drivers the disk form offers, plus which one the current default disk
     * uses so the form can preselect it. Falls back to the local driver when no
     * disk is flagged as default.
     */
    public function getDiskDrivers(): JsonResponse
    {
        $this->authorize('manage file disk');

        $drivers = [
            ['name' => 'Local', 'value' => 'local'],
            ['name' => 'Amazon S3', 'value' => 's3'],
            ['name' => 'S3 Compatible Storage', 'value' => 's3compat'],
            ['name' => 'Digital Ocean Spaces', 'value' => 'doSpaces'],
            ['name' => 'Dropbox', 'value' => 'dropbox'],
        ];

        return response()->json([
            'drivers' => $drivers,
            'default' => $this->defaultDisk()?->driver ?? 'local',
        ]);
    }

    /**
     * Which disk each of the three jobs currently uses: new media, generated
     * PDFs, and backups.
     *
     * A job with no setting of its own reads as the default disk, so the screen
     * shows what would actually be used rather than a blank. A stored setting
     * comes back as the string it was saved as, while the fallback is the
     * numeric id off the model -- so the three keys are not uniformly typed.
     */
    public function getDiskPurposes(): JsonResponse
    {
        $this->authorize('manage file disk');

        $fallback = $this->defaultDisk()?->id;

        return response()->json([
            'media_disk_id' => Setting::getSetting('media_disk_id') ?? $fallback,
            'pdf_disk_id' => Setting::getSetting('pdf_disk_id') ?? $fallback,
            'backup_disk_id' => Setting::getSetting('backup_disk_id') ?? $fallback,
        ]);
    }

    /**
     * Point one or more of those jobs at a different disk.
     *
     * Each key is written only if the body mentions it, so a caller may move
     * one job without disturbing the others; a key sent as null clears the
     * setting, which puts that job back on the default disk. Ids are checked
     * against the table, but nothing checks that the chosen disk is a sensible
     * home for the job -- pointing backups at a disk the backup subsystem does
     * not know surfaces only when a run is attempted.
     */
    public function updateDiskPurposes(Request $request): JsonResponse
    {
        $this->authorize('manage file disk');

        $purposes = ['media_disk_id', 'pdf_disk_id', 'backup_disk_id'];

        $request->validate(array_fill_keys($purposes, ['nullable', 'exists:file_disks,id']));

        foreach ($purposes as $purpose) {
            if ($request->has($purpose)) {
                Setting::setSetting($purpose, $request->input($purpose));
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * The single disk carrying the default flag, if there is one.
     */
    private function defaultDisk(): ?FileDisk
    {
        return FileDisk::query()->where('set_as_default', true)->first();
    }
}
