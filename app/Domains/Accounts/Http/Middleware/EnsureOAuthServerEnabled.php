<?php

namespace App\Domains\Accounts\Http\Middleware;

use App\Domains\Accounts\Application\OAuth\OAuthServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `oauth.enabled` alias: hides the OAuth server until something uses it.
 *
 * Every OAuth route answers 404 while no consumer of the server is switched
 * on, so an installation that never enabled one exposes nothing new.
 */
class EnsureOAuthServerEnabled
{
    public function __construct(
        private readonly OAuthServer $server,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->server->enabled(), 404);

        return $next($request);
    }
}
