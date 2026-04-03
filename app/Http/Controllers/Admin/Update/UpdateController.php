<?php

namespace App\Http\Controllers\Admin\Update;

use App\Http\Controllers\Controller;
use App\Services\Update\Updater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UpdateController extends Controller
{
    public function checkVersion(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

        set_time_limit(600);

        $channel = $request->get('channel', 'stable');
        $version = preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));

        return response()->json(Updater::checkForUpdate($version, $channel));
    }

    public function download(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

        $request->validate(['version' => 'required']);

        return response()->json([
            'success' => true,
            'path' => Updater::download($request->version),
        ]);
    }

    public function unzip(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

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
        $this->ensureOwner($request);

        $request->validate(['path' => 'required']);

        return response()->json([
            'success' => true,
            'path' => Updater::copyFiles($request->path),
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

        if (isset($request->deleted_files) && ! empty($request->deleted_files)) {
            Updater::deleteFiles($request->deleted_files);
        }

        return response()->json(['success' => true]);
    }

    public function migrate(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

        Updater::migrateUpdate();

        return response()->json(['success' => true]);
    }

    public function finish(Request $request): JsonResponse
    {
        $this->ensureOwner($request);

        $request->validate([
            'installed' => 'required',
            'version' => 'required',
        ]);

        return response()->json(Updater::finishUpdate($request->installed, $request->version));
    }

    private function ensureOwner(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isOwner()) {
            abort(401, 'You are not allowed to update this app.');
        }
    }
}
