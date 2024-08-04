<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $version = Setting::getSetting('version');

        $channel = Setting::getSetting('updater_channel');
        if (is_null($channel)) {
            $channel = 'stable';
            Setting::setSetting('updater_channel', 'stable'); // default.
        }

        return response()->json([
            'version' => $version,
            'channel' => $channel,
        ]);
    }
}
