<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CompanyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('user_company')) {
            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            $firstCompany = $user->companies()->first();

            // User has no companies — allow request through without company header
            if (! $firstCompany) {
                return $next($request);
            }

            // Super admin without company header — allow pass-through (admin mode)
            if ($user->isSuperAdmin() && ! $request->header('company')) {
                return $next($request);
            }

            if (! $request->header('company') || ! $user->hasCompany($request->header('company'))) {
                $request->headers->set('company', $firstCompany->id);
            }
        }

        return $next($request);
    }
}
