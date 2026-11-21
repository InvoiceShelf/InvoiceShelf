<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Domains\Accounts\Application\SsoHandoffs;
use App\Domains\Accounts\Application\SsoLogin;
use App\Domains\Accounts\Http\Requests\SsoHandoffRequest;
use App\Domains\Accounts\Http\Requests\SsoHandoffTokenRequest;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Single sign-on for the mobile app, on the public API (SsoHandoffs).
 *
 * The app starts a hand-off here, opens the returned address in the system
 * browser, and once the browser brings the code back to the app's link it
 * trades the code and its secret for a personal access token, the same kind
 * the password sign-in hands out.
 */
class SsoHandoffController extends Controller
{
    public function __construct(private readonly SsoHandoffs $handoffs) {}

    /**
     * Start a hand-off: the address to open in the system browser, the secret
     * that later proves this device started it, and the app link the browser
     * will be sent back to.
     */
    public function store(SsoHandoffRequest $request): JsonResponse
    {
        abort_unless(SsoLogin::enabled(), 404);

        $handoff = $this->handoffs->start($request->device_name);

        return response()->json([
            'id' => $handoff['id'],
            'secret' => $handoff['secret'],
            'url' => route('sso.app', ['handoff' => $handoff['id']]),
            'app_link' => config('invoiceshelf.client.app_link'),
        ], 201);
    }

    /**
     * Trade the code the browser brought back, with the secret, for a token
     * named after the device that started the hand-off.
     */
    public function token(SsoHandoffTokenRequest $request, string $handoff): JsonResponse
    {
        abort_unless(SsoLogin::enabled(), 404);

        $signedIn = $this->handoffs->redeem($handoff, $request->secret, $request->code);

        if ($signedIn === null) {
            throw ValidationException::withMessages(['code' => ['This sign-in can no longer be completed. Please sign in again.']]);
        }

        return response()->json([
            'type' => 'Bearer',
            'token' => $signedIn['user']->createToken($signedIn['device_name'])->plainTextToken,
        ]);
    }
}
