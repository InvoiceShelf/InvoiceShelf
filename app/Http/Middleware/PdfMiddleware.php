<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PdfMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response|RedirectResponse)  $next
     * @return \Illuminate\Http\Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check() || Auth::guard('sanctum')->check() || Auth::guard('customer')->check()) {
            return $next($request);
        }

        return redirect('/login');
    }
}
