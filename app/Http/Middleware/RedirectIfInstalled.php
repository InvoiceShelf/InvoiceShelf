<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Space\InstallUtils;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallUtils::dbMarkerExists()) {
            if (Setting::getSetting('profile_complete') === 'COMPLETED') {
                return redirect('login');
            }
        }

        return $next($request);
    }
}
