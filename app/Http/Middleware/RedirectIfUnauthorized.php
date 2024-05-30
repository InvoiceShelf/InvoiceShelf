<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfUnauthorized
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null): Response
    {
        if (Auth::guard($guard)->check()) {
            return $next($request);
        }

        return redirect('/login');
    }
}
