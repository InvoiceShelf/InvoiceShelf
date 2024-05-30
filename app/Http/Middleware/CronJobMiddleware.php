<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;

class CronJobMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('x-authorization-token') && $request->header('x-authorization-token') == config('services.cron_job.auth_token')) {
            return $next($request);
        }

        return response()->json(['unauthorized'], 401);
    }
}
