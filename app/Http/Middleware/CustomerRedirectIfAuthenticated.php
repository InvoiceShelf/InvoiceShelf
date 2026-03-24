<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerRedirectIfAuthenticated
{
    /**
     * Redirect customers away from "guest" routes (e.g. login) when already authenticated.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $guard ??= 'customer';

        if (Auth::guard($guard)->check()) {
            return redirect()->to(RouteServiceProvider::CUSTOMER_HOME);
        }

        return $next($request);
    }
}
