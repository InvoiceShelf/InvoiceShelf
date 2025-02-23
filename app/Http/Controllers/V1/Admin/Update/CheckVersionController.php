<?php

namespace App\Http\Controllers\V1\Admin\Update;

use App\Http\Controllers\Controller;
use App\Space\Updater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CheckVersionController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        if ((! $request->user()) || (! $request->user()->isOwner())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this app.',
            ], 401);
        }

        set_time_limit(600); // 10 minutes

        $channel = $request->get('channel', 'stable');
        $version = preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));
        $response = Updater::checkForUpdate($version, $channel);

        return response()->json($response);
    }
}
