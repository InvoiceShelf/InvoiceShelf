<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next, $guard = null): Response
    {
        if (Auth::guard($guard)->guest() || ! Auth::user()->isSuperAdmin()) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        return $next($request);
    }
}
