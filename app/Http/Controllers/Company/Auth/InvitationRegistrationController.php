<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvitationRegistrationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {}

    /**
     * Get invitation details by token (public endpoint for registration page).
     */
    public function details(string $token): JsonResponse
    {
        $invitation = CompanyInvitation::where('token', $token)
            ->pending()
            ->with(['company', 'role'])
            ->first();

        if (! $invitation) {
            return response()->json([
                'error' => 'Invitation not found or expired.',
            ], 404);
        }

        return response()->json([
            'email' => $invitation->email,
            'company_name' => $invitation->company->name,
            'role_name' => $invitation->role->title,
        ]);
    }

    /**
     * Register a new user and auto-accept the invitation.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'invitation_token' => 'required|string',
        ]);

        $invitation = CompanyInvitation::where('token', $request->invitation_token)
            ->pending()
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation_token' => ['Invitation not found or expired.'],
            ]);
        }

        if ($invitation->email !== $request->email) {
            throw ValidationException::withMessages([
                'email' => ['Email does not match the invitation.'],
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $this->invitationService->accept($invitation, $user);

        return response()->json([
            'type' => 'Bearer',
            'token' => $user->createToken('web')->plainTextToken,
        ]);
    }
}
