<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UpdateSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(SettingRequest $request)
    {
        $this->authorize('manage settings');

        Setting::setSettings($request->settings);

        return response()->json([
            'success' => true,
            $request->settings,
        ]);
    }
}
