<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CronJobMiddleware
{
    /**
     * Let the caller through only when it presents the configured token.
     *
     * An install that has configured no token is not using this endpoint, so
     * it is refused before any comparison rather than matching the empty
     * string. The comparison itself is on strings and in constant time: a
     * loose one would have let any header through had the configured value
     * ever been boolean true.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.cron_job.auth_token');

        if (! is_scalar($configured) || (string) $configured === '') {
            return response()->json(['unauthorized'], 401);
        }

        $presented = $request->header('x-authorization-token');

        if (! is_string($presented) || $presented === '') {
            return response()->json(['unauthorized'], 401);
        }

        if (hash_equals((string) $configured, $presented)) {
            return $next($request);
        }

        return response()->json(['unauthorized'], 401);
    }
}
