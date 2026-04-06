<?php

namespace App\Http\Controllers\V1\Admin\Update;

use App\Http\Controllers\Controller;
use App\Space\Updater;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CleanFilesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * Removes any file that does not appear in the release manifest.json,
     * replacing the legacy hardcoded deleted_files list. Falls back to the
     * legacy deleteFiles() behaviour when the request still ships a
     * deleted_files payload (backward compatibility for older release
     * packages built before the manifest was introduced).
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        if ((! $request->user()) || (! $request->user()->isOwner())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this app.',
            ], 401);
        }

        // Backward compatibility: a release package built before the manifest
        // was introduced may still ship a deleted_files list. Honour it.
        if (isset($request->deleted_files) && ! empty($request->deleted_files)) {
            Updater::deleteFiles($request->deleted_files);
        }

        $result = Updater::cleanStaleFiles();

        return response()->json($result);
    }
}
