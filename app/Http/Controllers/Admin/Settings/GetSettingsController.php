<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingRequest;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class GetSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     *
     * @throws AuthorizationException
     */
    public function __invoke(GetSettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $setting = Setting::getSetting($request->key);

        return response()->json([
            $request->key => $setting,
        ]);
    }
}
