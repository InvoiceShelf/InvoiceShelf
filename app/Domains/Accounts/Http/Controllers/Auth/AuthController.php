<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Http\Requests\LoginRequest;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use App\Platform\Operations\Managed\ManagedMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Bearer-token endpoints for staff accounts.
 *
 * These sit on the public API prefix and serve the admin SPA as well as any
 * external client: trade a password for a personal access token, hand that
 * token back in, or ask whether the current request still carries one.
 *
 * Nothing here is tenant-scoped. A staff address is unique installation-wide,
 * so the company is resolved after sign-in from the request header, never
 * chosen at the door the way the customer portal does it.
 */
class AuthController extends Controller
{
    /**
     * One answer covers both an address nobody holds and a mistyped password,
     * so the two cannot be told apart from the reply.
     */
    private const REJECTED = 'The provided credentials are incorrect.';

    /**
     * Trade an email/password pair for a personal access token.
     *
     * The token is named after the calling device, which is what later makes
     * per-device revocation meaningful.
     */
    public function login(LoginRequest $request)
    {
        $staff = $this->staffHolding($request->username);

        if ($staff === null || ! Hash::check($request->password, $staff->password)) {
            throw ValidationException::withMessages(['email' => [self::REJECTED]]);
        }

        // Deliberately reached only once the pair has been proven, so nobody
        // can spend an invitation by guessing at somebody else's password.
        if (! ManagedMode::readOnly()) {
            $this->redeemPendingInvitation($request, $staff);
        }

        $minted = $staff->createToken($request->device_name);

        return response()->json([
            'type' => 'Bearer',
            'token' => $minted->plainTextToken,
        ]);
    }

    /**
     * Drop the token that carried this request.
     *
     * Quirk kept as is: exactly one token is revoked, never the account's
     * whole set, so the caller's other devices stay signed in. And a caller
     * authenticated by session cookie rather than a bearer token holds a
     * transient token that has nothing to delete, so that request errors out
     * instead of closing the session.
     */
    public function logout(Request $request)
    {
        $carrier = $request->user()->currentAccessToken();
        $carrier->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Report whether the caller is signed in.
     *
     * The bare boolean body is deliberate: the route already sits behind the
     * API guard, so the SPA reads this purely as a liveness ping.
     */
    public function check()
    {
        return Auth::check();
    }

    /**
     * Find the staff account holding the submitted address.
     *
     * The comparison runs against the lower-cased column so that capitalising
     * an address differently from how it was stored still gets the account
     * in, on every database engine the app supports.
     */
    private function staffHolding($submitted): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($submitted)])
            ->first();
    }

    /**
     * Accept an invitation carried alongside the credentials, when one is
     * still live.
     *
     * A token that is unknown, already spent or past its expiry is passed
     * over in silence. Sign-in itself is never held up by it.
     */
    private function redeemPendingInvitation(LoginRequest $request, User $staff): void
    {
        $offered = $request->input('invitation_token');

        if (! $offered) {
            return;
        }

        $invitation = CompanyInvitation::query()
            ->where('token', $offered)
            ->pending()
            ->first();

        if ($invitation !== null) {
            app(InvitationService::class)->accept($invitation, $staff);
        }
    }
}
