<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSessionDomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (empty(config('session.domain'))) {
            config(['session.domain' => $request->getHost()]);
        }

        return $next($request);
    }
}
