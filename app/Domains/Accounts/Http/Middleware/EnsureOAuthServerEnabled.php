<?php

namespace App\Domains\Accounts\Http\Middleware;

use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use App\Domains\Accounts\Application\OAuth\OAuthServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `oauth.enabled` alias: hides the OAuth server until something uses it.
 *
 * Every OAuth route answers 404 while no consumer of the server is switched
 * on, so an installation that never enabled one exposes nothing new. While
 * one is on but the signing keys are missing, it answers 503 with an OAuth
 * error instead of failing deep inside the token endpoint.
 */
class EnsureOAuthServerEnabled
{
    public function __construct(
        private readonly OAuthServer $server,
        private readonly OAuthKeyManager $keys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->server->enabled(), 404);

        if (! $this->keys->ready()) {
            return response()->json([
                'error' => 'temporarily_unavailable',
                'error_description' => 'The OAuth signing keys are missing. An administrator must create them.',
            ], 503);
        }

        return $next($request);
    }
}
