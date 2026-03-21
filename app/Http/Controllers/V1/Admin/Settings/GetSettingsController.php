<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GetSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
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
