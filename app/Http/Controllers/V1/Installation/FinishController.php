<?php

namespace App\Http\Controllers\V1\Installation;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Space\InstallUtils;

class FinishController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        if (! InstallUtils::createDbMarker()) {
            \Log::error('Install: Unable to create db marker.');
        }

        return response()->json(['success' => true]);
    }
}
