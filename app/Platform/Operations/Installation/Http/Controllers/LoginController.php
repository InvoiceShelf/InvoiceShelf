<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Authentication\InstallWizardAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the wizard in as the administrator the seeders just created, so the
 * remaining steps can call the authenticated API.
 */
class LoginController extends Controller
{
    /**
     * There is exactly one candidate at this point in the install: the first
     * platform administrator, and the first company attached to them. Any
     * browser session that got this far is thrown away — the wizard carries on
     * with a bearer token limited to the wizard ability that expires after
     * installer.wizard_token_ttl minutes, and only one such token is ever live
     * at a time.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::where('role', 'super admin')->first();

        if ($user === null) {
            return response()->json([
                'message' => 'Super admin user not found.',
            ], 404);
        }

        $company = $user->companies()->first();

        if ($company === null) {
            return response()->json([
                'message' => 'Super admin company not found.',
            ], 422);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $user->tokens()->where('name', InstallWizardAuth::TOKEN_NAME)->delete();

        return response()->json([
            'success' => true,
            'type' => 'Bearer',
            'token' => $user->createToken(
                InstallWizardAuth::TOKEN_NAME,
                [InstallWizardAuth::TOKEN_ABILITY],
                now()->addMinutes(config('installer.wizard_token_ttl')),
            )->plainTextToken,
            'user' => $user,
            'company' => $company,
        ]);
    }
}
