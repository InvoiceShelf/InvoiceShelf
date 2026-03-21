<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingsRequest;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCompanySettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(GetSettingsRequest $request)
    {
        $settings = CompanySetting::getSettings((array) $request->settings, $request->header('company'));

        return response()->json($settings);
    }
}
