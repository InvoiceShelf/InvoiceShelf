<?php

namespace App\Http\Controllers\V1\Admin\Update;

use App\Http\Controllers\Controller;
use App\Space\Updater;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class DeleteFilesController extends Controller
{
    /**
     * Handle the incoming request.
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

        // Backward compatibility: use the explicit deleted_files list only when the
        // release ships no manifest.json (same-line v2 updates). When a manifest is
        // present (e.g. the v3 release), clean every stale file not in the manifest.
        if (! File::exists(base_path('manifest.json'))
            && isset($request->deleted_files)
            && ! empty($request->deleted_files)) {
            Updater::deleteFiles($request->deleted_files);

            return response()->json(['success' => true, 'cleaned' => 0]);
        }

        return response()->json(Updater::cleanStaleFiles());
    }
}
