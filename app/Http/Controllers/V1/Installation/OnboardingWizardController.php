<?php

namespace App\Http\Controllers\V1\Installation;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Space\InstallUtils;
use Illuminate\Http\Request;

class OnboardingWizardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStep(Request $request)
    {
        if (! InstallUtils::dbMarkerExists()) {
            return response()->json([
                'profile_complete' => 0,
            ]);
        }

        return response()->json([
            'profile_complete' => Setting::getSetting('profile_complete'),
        ]);
    }

    public function updateStep(Request $request)
    {
        $setting = Setting::getSetting('profile_complete');

        if ($setting === 'COMPLETED') {
            return response()->json([
                'profile_complete' => $setting,
            ]);
        }

        Setting::setSetting('profile_complete', $request->profile_complete);

        return response()->json([
            'profile_complete' => Setting::getSetting('profile_complete'),
        ]);
    }
}
