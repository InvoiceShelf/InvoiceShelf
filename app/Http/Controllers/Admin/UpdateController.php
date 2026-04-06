<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Update\Updater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UpdateController extends Controller
{
    public function checkVersion(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        set_time_limit(600);

        $channel = $request->get('channel', 'stable');
        $version = preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));

        return response()->json(Updater::checkForUpdate($version, $channel));
    }

    public function download(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $request->validate(['version' => 'required']);

        return response()->json([
            'success' => true,
            'path' => Updater::download($request->version),
        ]);
    }

    public function unzip(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $request->validate(['path' => 'required']);

        try {
            return response()->json([
                'success' => true,
                'path' => Updater::unzip($request->path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function copy(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $request->validate(['path' => 'required']);

        return response()->json([
            'success' => true,
            'path' => Updater::copyFiles($request->path),
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        return $this->clean($request);
    }

    public function clean(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        // Backward compatibility: use deleted_files when no manifest exists
        if (! File::exists(base_path('manifest.json'))
            && isset($request->deleted_files)
            && ! empty($request->deleted_files)) {
            Updater::deleteFiles($request->deleted_files);

            return response()->json(['success' => true, 'cleaned' => 0]);
        }

        $result = Updater::cleanStaleFiles();

        return response()->json($result);
    }

    public function migrate(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        Updater::migrateUpdate();

        return response()->json(['success' => true]);
    }

    public function finish(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $request->validate([
            'installed' => 'required',
            'version' => 'required',
        ]);

        return response()->json(Updater::finishUpdate($request->installed, $request->version));
    }

    private function ensureSuperAdmin(): void
    {
        $this->authorize('manage update app');
    }
}
