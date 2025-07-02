<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CompanyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('user_company')) {
            $user = $request->user();

            if ($user && (! $request->header('company')) || (! $user->hasCompany($request->header('company')))) {
                $firstCompany = $user->companies()->first();
                if ($firstCompany) {
                    $request->headers->set('company', $firstCompany->id);
                }
            }
        }

        return $next($request);
    }
}
