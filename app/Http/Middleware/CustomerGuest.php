<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerGuest
{
    /**
     * Guest-only routes for the customer guard (same behavior as {@see CustomerRedirectIfAuthenticated}).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        return app(CustomerRedirectIfAuthenticated::class)->handle($request, $next, $guard);
    }
}
