<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

            if ((! $request->header('company')) || (! $user->hasCompany($request->header('company')))) {
                $request->headers->set('company', $user->companies()->first()->id);
            }
        }

        return $next($request);
    }
}
