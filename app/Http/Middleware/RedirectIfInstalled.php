<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;
use InvoiceShelf\Models\Setting;
use InvoiceShelf\Space\InstallUtils;

class RedirectIfInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
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
