<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiskEnvironmentRequest;
use App\Http\Resources\FileDiskResource;
use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\FileDiskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DiskController extends Controller
{
    public function __construct(
        private readonly FileDiskService $fileDiskService,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws AuthorizationException
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage file disk');

        $limit = $request->has('limit') ? $request->limit : 5;
        $disks = FileDisk::applyFilters($request->all())
            ->latest()
            ->paginateData($limit);

        return FileDiskResource::collection($disks);
    }

    /**
     * @return JsonResponse
     *
     * @throws AuthorizationException
     * @throws AuthorizationException
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
     * @throws AuthorizationException
     */
    public function update(FileDisk $disk, Request $request): JsonResponse|FileDiskResource
    {
        $this->authorize('manage file disk');

        $credentials = $request->credentials;
        $driver = $request->driver;

        if ($credentials && $driver && $disk->type !== 'SYSTEM') {
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
     * @param  Request  $request
     *
     * @throws AuthorizationException
     * @throws AuthorizationException
     */
    public function show($disk): JsonResponse
    {

        $this->authorize('manage file disk');

        $diskData = [];
        switch ($disk) {
            case 'local':
                // Path is relative to storage/app/.
                // e.g., "backups" resolves to storage/app/backups/ at runtime.
                $diskData = [
                    'root' => '',
                ];

                break;

            case 's3':
                $diskData = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'root' => '',
                ];

                break;

            case 's3compat':
                $diskData = [
                    'endpoint' => '',
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'root' => '',
                ];

            case 'doSpaces':
                $diskData = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'endpoint' => '',
                    'root' => '',
                ];

                break;

            case 'dropbox':
                $diskData = [
                    'token' => '',
                    'key' => '',
                    'secret' => '',
                    'app' => '',
                    'root' => '',
                ];

                break;
        }

        $data = array_merge($diskData);

        return response()->json($data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  FileDisk  $taxType
     *
     * @throws AuthorizationException
     * @throws AuthorizationException
     */
    public function destroy(FileDisk $disk): JsonResponse
    {
        $this->authorize('manage file disk');

        if ($disk->type === 'SYSTEM') {
            return respondJson('not_allowed', 'System disks cannot be deleted.');
        }

        if ($disk->setAsDefault()) {
            return respondJson('not_allowed', 'The default disk cannot be deleted.');
        }

        $prefix = env('DYNAMIC_DISK_PREFIX', 'temp_');
        $diskName = $prefix.$disk->driver;
        $mediaCount = DB::table('media')
            ->where('disk', $diskName)
            ->orWhere('disk', $disk->driver)
            ->count();

        if ($mediaCount > 0) {
            return respondJson('disk_has_files', 'Cannot delete this disk — it contains '.$mediaCount.' file(s). Migrate files first.');
        }

        $disk->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthorizationException
     */
    public function getDiskDrivers(): JsonResponse
    {
        $this->authorize('manage file disk');

        $drivers = [
            [
                'name' => 'Local',
                'value' => 'local',
            ],
            [
                'name' => 'Amazon S3',
                'value' => 's3',
            ],
            [
                'name' => 'S3 Compatible Storage',
                'value' => 's3compat',
            ],
            [
                'name' => 'Digital Ocean Spaces',
                'value' => 'doSpaces',
            ],
            [
                'name' => 'Dropbox',
                'value' => 'dropbox',
            ],
        ];

        $defaultDisk = FileDisk::where('set_as_default', true)->first();

        return response()->json([
            'drivers' => $drivers,
            'default' => $defaultDisk?->driver ?? 'local',
        ]);
    }

    public function getDiskPurposes(): JsonResponse
    {
        $this->authorize('manage file disk');

        $defaultDisk = FileDisk::where('set_as_default', true)->first();
        $defaultId = $defaultDisk?->id;

        return response()->json([
            'media_disk_id' => Setting::getSetting('media_disk_id') ?? $defaultId,
            'pdf_disk_id' => Setting::getSetting('pdf_disk_id') ?? $defaultId,
            'backup_disk_id' => Setting::getSetting('backup_disk_id') ?? $defaultId,
        ]);
    }

    public function updateDiskPurposes(Request $request): JsonResponse
    {
        $this->authorize('manage file disk');

        $request->validate([
            'media_disk_id' => 'nullable|exists:file_disks,id',
            'pdf_disk_id' => 'nullable|exists:file_disks,id',
            'backup_disk_id' => 'nullable|exists:file_disks,id',
        ]);

        if ($request->has('media_disk_id')) {
            Setting::setSetting('media_disk_id', $request->media_disk_id);
        }

        if ($request->has('pdf_disk_id')) {
            Setting::setSetting('pdf_disk_id', $request->pdf_disk_id);
        }

        if ($request->has('backup_disk_id')) {
            Setting::setSetting('backup_disk_id', $request->backup_disk_id);
        }

        return response()->json(['success' => true]);
    }
}
