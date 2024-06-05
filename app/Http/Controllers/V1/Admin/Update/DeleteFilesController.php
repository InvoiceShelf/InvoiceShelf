<?php

namespace App\Http\Controllers\V1\Admin\Update;

use App\Http\Controllers\Controller;
use App\Space\Updater;
use Illuminate\Http\Request;

class DeleteFilesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        if ((! $request->user()) || (! $request->user()->isOwner())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this app.',
            ], 401);
        }

        if (isset($request->deleted_files) && ! empty($request->deleted_files)) {
            Updater::deleteFiles($request->deleted_files);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
