<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerPortalMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response|RedirectResponse)  $next
     * @return \Illuminate\Http\Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('customer')->user();

        if (! $user->enable_portal) {
            Auth::guard('customer')->logout();

            return response('Unauthorized.', 401);
        }

        return $next($request);
    }
}
