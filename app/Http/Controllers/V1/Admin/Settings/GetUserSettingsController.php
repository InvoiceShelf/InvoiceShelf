<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingsRequest;

class GetUserSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\GetSettingsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(GetSettingsRequest $request)
    {
        $user = $request->user();

        return response()->json($user->getSettings($request->settings));
    }
}
