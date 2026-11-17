<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Sign-in with SSO for the mobile app.
 *
 * The browser flow ends in a web session, which the app cannot use. The app
 * starts a hand-off instead and keeps its secret, signs in in the system
 * browser (OAuth2Controller::app), and the callback sends that browser back
 * to the app's link (`invoiceshelf.client.app_link`) with a one-time code.
 * Only the holder of the secret can trade the code for an access token, as
 * in RFC 8252: a sign-in address passed to somebody else gets the sender
 * nothing, because the code goes to the device that opened it and the secret
 * never leaves the device that started it.
 */
final class OAuth2Handoffs
{
    /**
     * Seconds the sign-in in the browser may take.
     */
    public const SIGN_IN_SECONDS = 600;

    /**
     * Seconds the app has to trade the code once the browser brought it back.
     */
    public const CODE_SECONDS = 120;

    /**
     * Starts a hand-off for the named device: its ID, which may travel in
     * URLs, and its secret, which only the app keeps.
     *
     * @return array{id: string, secret: string}
     */
    public function start(string $deviceName): array
    {
        $id = Str::random(40);
        $secret = Str::random(64);

        Cache::put($this->key($id), [
            'secret' => hash('sha256', $secret),
            'device_name' => $deviceName,
            'user_id' => null,
            'code' => null,
        ], self::SIGN_IN_SECONDS);

        return ['id' => $id, 'secret' => $secret];
    }

    /**
     * Whether the hand-off exists and is still waiting for its sign-in.
     */
    public function pending(string $id): bool
    {
        $handoff = Cache::get($this->key($id));

        return is_array($handoff) && $handoff['user_id'] === null;
    }

    /**
     * Records who signed in and returns the one-time code for the app. Null
     * when the hand-off is gone or already has its sign-in.
     */
    public function complete(string $id, User $user): ?string
    {
        if (! $this->pending($id)) {
            return null;
        }

        $code = Str::random(64);

        Cache::put($this->key($id), [
            ...Cache::get($this->key($id)),
            'user_id' => $user->id,
            'code' => hash('sha256', $code),
        ], self::CODE_SECONDS);

        return $code;
    }

    /**
     * The signed-in user and the device to name the token after, for the
     * right secret and code. One try: any answer, right or wrong, ends the
     * hand-off.
     *
     * @return array{user: User, device_name: string}|null
     */
    public function redeem(string $id, string $secret, string $code): ?array
    {
        $handoff = Cache::pull($this->key($id));

        if (! is_array($handoff) || $handoff['user_id'] === null
            || ! hash_equals($handoff['secret'], hash('sha256', $secret))
            || ! hash_equals((string) $handoff['code'], hash('sha256', $code))) {
            return null;
        }

        $user = User::query()->find($handoff['user_id']);

        return $user ? ['user' => $user, 'device_name' => $handoff['device_name']] : null;
    }

    private function key(string $id): string
    {
        return "oauth2_handoff:{$id}";
    }
}
