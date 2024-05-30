<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Auth;
use Closure;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null): Response
    {
        if (Auth::guard($guard)->guest() || ! Auth::user()->isSuperAdminOrAdmin()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized.', 401);
            } else {
                return response()->json(['error' => 'user_is_not_admin'], 404);
            }
        }

        return $next($request);
    }
}
