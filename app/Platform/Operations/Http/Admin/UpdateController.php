<?php

namespace App\Platform\Operations\Http\Admin;

use App\Platform\Http\Controller;
use App\Platform\Operations\Update\Updater;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Drives the in-app upgrade one hop at a time.
 *
 * The browser walks the pipeline itself — check, download, unzip, copy, clean,
 * migrate, finish — so no single request has to survive a whole release swap.
 * Refusing the pipeline on containerized installs is the route group's job
 * ("not-containerized" middleware), not this controller's.
 */
class UpdateController extends Controller
{
    /**
     * Head room, in seconds, for the release-server round trip.
     */
    private const CHECK_TIME_BUDGET = 600;

    public function checkVersion(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        set_time_limit(self::CHECK_TIME_BUDGET);

        $channel = $request->get('channel', 'stable');

        return response()->json(
            Updater::checkForUpdate($this->versionOnDisk(), $channel)
        );
    }

    public function download(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        $request->validate(['version' => 'required']);

        return $this->completed(Updater::download($request->input('version')));
    }

    public function unzip(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        $request->validate(['path' => 'required']);

        try {
            return $this->completed(Updater::unzip($request->input('path')));
        } catch (Exception $failure) {
            return response()->json([
                'success' => false,
                'error' => $failure->getMessage(),
            ], 500);
        }
    }

    public function copy(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        $request->validate(['path' => 'required']);

        // The copy step answers with a boolean, which has always travelled back
        // to the client under the "path" key. Left alone on purpose.
        return $this->completed(Updater::copyFiles($request->input('path')));
    }

    public function delete(Request $request): JsonResponse
    {
        return $this->clean($request);
    }

    public function clean(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        $legacyList = $request->input('deleted_files');

        // Releases from before the manifest era shipped an explicit removal
        // list instead of a manifest; honour it only while no manifest exists.
        if (! empty($legacyList) && ! File::exists(base_path('manifest.json'))) {
            Updater::deleteFiles($legacyList);

            return response()->json(['success' => true, 'cleaned' => 0]);
        }

        return response()->json(Updater::cleanStaleFiles());
    }

    /**
     * Run the pending migrations, unless the schema guard refuses first.
     *
     * A refusal is the operator's problem to fix, not a crash: it comes back
     * with the guard's explanation in the same shape the unzip step uses for
     * its own failures.
     */
    public function migrate(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        // Some migrations rewrite data in every company (role presets, for
        // one), which outlasts PHP's default limit on a large install.
        set_time_limit(self::CHECK_TIME_BUDGET);

        try {
            Updater::migrateUpdate();
        } catch (Exception $failure) {
            return response()->json([
                'success' => false,
                'error' => $failure->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true]);
    }

    public function finish(Request $request): JsonResponse
    {
        $this->authorizeUpdates();

        $request->validate([
            'installed' => 'required',
            'version' => 'required',
        ]);

        return response()->json(
            Updater::finishUpdate($request->input('installed'), $request->input('version'))
        );
    }

    /**
     * Only the platform administrator may touch any part of the pipeline.
     */
    private function authorizeUpdates(): void
    {
        $this->authorize('manage update app');
    }

    /**
     * The shared "this step worked, here is what it produced" envelope.
     */
    private function completed(mixed $outcome): JsonResponse
    {
        return response()->json([
            'success' => true,
            'path' => $outcome,
        ]);
    }

    private function versionOnDisk(): string
    {
        return preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));
    }
}
