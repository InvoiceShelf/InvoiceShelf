<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InstallWizardAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::where('role', 'super admin')->first();
        if (! $user) {
            return response()->json([
                'message' => 'Super admin user not found.',
            ], 404);
        }

        $company = $user->companies()->first();
        if (! $company) {
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
        $token = $user->createToken(
            InstallWizardAuth::TOKEN_NAME,
            [InstallWizardAuth::TOKEN_ABILITY],
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'type' => 'Bearer',
            'token' => $token,
            'user' => $user,
            'company' => $company,
        ]);
    }
}
