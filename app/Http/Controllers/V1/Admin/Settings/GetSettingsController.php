<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingRequest;
use App\Models\Setting;
use Illuminate\Http\Request;

class GetSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(GetSettingRequest $request)
    {
        $this->authorize('manage settings');

        $setting = Setting::getSetting($request->key);

        return response()->json([
            $request->key => $setting,
        ]);
    }
}
