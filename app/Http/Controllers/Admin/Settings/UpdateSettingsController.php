<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class UpdateSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     *
     * @throws AuthorizationException
     */
    public function __invoke(SettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        Setting::setSettings($request->settings);

        return response()->json([
            'success' => true,
            $request->settings,
        ]);
    }
}
