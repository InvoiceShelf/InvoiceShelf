<?php

namespace App\Platform\Operations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the externally callable cron webhook: the caller proves itself
 * with a shared token carried in a request header.
 */
class CronJobMiddleware
{
    /**
     * Name of the header the external scheduler is expected to send.
     */
    private const TOKEN_HEADER = 'x-authorization-token';

    /**
     * Forward the request only when the presented token matches the one in
     * the configuration; anything else is refused outright.
     *
     * An install that has configured no token is not using this endpoint, so
     * it is refused before any comparison: the alternative is a deployment
     * where the secret is the empty string. The comparison itself is on
     * strings and in constant time, because a loose one would have let any
     * header through had the configured value ever been boolean true.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.cron_job.auth_token');

        if (! is_scalar($configured) || (string) $configured === '') {
            return $this->refuse();
        }

        $presented = $request->header(self::TOKEN_HEADER);

        if (! is_string($presented) || $presented === '') {
            return $this->refuse();
        }

        return hash_equals((string) $configured, $presented)
            ? $next($request)
            : $this->refuse();
    }

    /**
     * The refusal body is a bare JSON array, not an object — callers of the
     * webhook match on the status code, so the shape stays as it is.
     */
    private function refuse(): Response
    {
        return response()->json(['unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
