<?php

namespace App\Http\Controllers\Company\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingsRequest;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

class UserSettingsController extends Controller
{
    public function show(GetSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->getSettings((array) $request->settings));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->setSettings($request->settings);

        return response()->json([
            'success' => true,
        ]);
    }
}
