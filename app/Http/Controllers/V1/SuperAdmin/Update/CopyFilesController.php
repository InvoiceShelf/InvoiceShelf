<?php

namespace App\Http\Controllers\V1\SuperAdmin\Update;

use App\Http\Controllers\Controller;
use App\Services\Update\Updater;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CopyFilesController extends Controller
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

        $request->validate([
            'path' => 'required',
        ]);

        $path = Updater::copyFiles($request->path);

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }
}
