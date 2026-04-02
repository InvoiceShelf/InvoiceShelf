<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Space\InstallUtils;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! InstallUtils::isDbCreated() || Setting::getSetting('profile_complete') !== 'COMPLETED') {
                return redirect('/installation');
            }
        } catch (\Exception $e) {
            return redirect('/installation');
        }

        return $next($request);
    }
}
