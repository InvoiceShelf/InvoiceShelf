<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tracks how far through the setup wizard the operator has walked, so a
 * reloaded browser resumes on the step it left off.
 */
class OnboardingWizardController extends Controller
{
    /**
     * Terminal value of the profile_complete setting.
     */
    private const FINISHED = 'COMPLETED';

    /**
     * Until the schema exists there is nowhere to read progress from, so the
     * very first step and the default language are answered from thin air.
     */
    public function getStep(Request $request): JsonResponse
    {
        if (! InstallationState::isDbCreated()) {
            return response()->json([
                'profile_complete' => 0,
                'profile_language' => 'en',
            ]);
        }

        return response()->json([
            'profile_complete' => Setting::getSetting('profile_complete'),
            'profile_language' => Setting::getSetting('profile_language'),
        ]);
    }

    /**
     * Record progress, unless the wizard has already run to completion — a
     * finished install must not be walked backwards into the setup flow.
     */
    public function updateStep(Request $request): JsonResponse
    {
        $step = Setting::getSetting('profile_complete');

        if ($step !== self::FINISHED) {
            if ($request->input('profile_complete') === self::FINISHED) {
                InstallationState::complete();
            } else {
                Setting::setSetting('profile_complete', $request->input('profile_complete'));
            }

            $step = Setting::getSetting('profile_complete');
        }

        return response()->json([
            'profile_complete' => $step,
        ]);
    }

    public function saveLanguage(Request $request): JsonResponse
    {
        Setting::setSetting('profile_language', $request->input('profile_language'));

        return response()->json([
            'profile_language' => Setting::getSetting('profile_language'),
        ]);
    }
}
