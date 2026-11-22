<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Single sign-on for the mobile app, whatever protocol is behind it.
 *
 * The browser flow ends in a web session, which the app cannot use. The app
 * starts a hand-off instead and keeps its secret, signs in in the system
 * browser (SsoController::app, which first asks the person there to
 * confirm), and the callback sends that browser back to the app's link
 * (`invoiceshelf.client.app_link`) with a one-time code. Only the holder of
 * the secret can trade the code for an access token, as in RFC 8252: the
 * code goes to the device that opened the sign-in, and the secret never
 * leaves the device that started it.
 */
final class SsoHandoffs
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
        return $this->deviceName($id) !== null;
    }

    /**
     * The name of the device that started the hand-off, while it is still
     * waiting for its sign-in; null otherwise. The app chose it, so it is
     * not to be trusted as markup.
     */
    public function deviceName(string $id): ?string
    {
        $handoff = Cache::get($this->key($id));

        return is_array($handoff) && $handoff['user_id'] === null ? (string) $handoff['device_name'] : null;
    }

    /**
     * Ends a hand-off that is still waiting for its sign-in, as when the
     * person in the browser says they did not start it.
     */
    public function cancel(string $id): void
    {
        if ($this->pending($id)) {
            Cache::forget($this->key($id));
        }
    }

    /**
     * Records who signed in and returns the one-time code for the app. Null
     * when the hand-off is gone or already has its sign-in.
     *
     * Two callbacks for one hand-off may both find it waiting. Only the one
     * that adds the completion marker first goes on, so a hand-off is
     * completed once, for one user.
     */
    public function complete(string $id, User $user): ?string
    {
        $handoff = Cache::get($this->key($id));

        if (! is_array($handoff) || $handoff['user_id'] !== null
            || ! Cache::add($this->key($id).':completed', true, self::SIGN_IN_SECONDS)) {
            return null;
        }

        $code = Str::random(64);

        Cache::put($this->key($id), [
            ...$handoff,
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

    /**
     * Where to send the browser back to the app with the outcome of its
     * hand-off: a one-time `code`, or an `error` (an SsoLogin error code, or
     * "cancelled"). Never chosen by a client: the link is server
     * configuration.
     *
     * @param  array<string, string>  $outcome
     */
    public function appLink(string $id, array $outcome): string
    {
        return config('invoiceshelf.client.app_link').'?'.http_build_query(['handoff' => $id, ...$outcome]);
    }

    private function key(string $id): string
    {
        return "sso_handoff:{$id}";
    }
}
