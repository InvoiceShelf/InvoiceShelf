<?php

namespace App\Domains\Accounts\Http\Middleware;

use App\Domains\Accounts\Application\PostLoginRedirect;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The `guest` alias: keeps signed-in staff off the sign-in pages.
 *
 * Rather than refusing, it forwards the caller on, so hitting the login page
 * a second time in the same browser simply lands where they were already
 * headed: the page named by a safe `next` parameter, or the dashboard.
 */
class RedirectIfAuthenticated
{
    /**
     * Pass guests through; send anyone already holding a session onwards.
     *
     * With no guard named on the route the default one is consulted, which
     * means a customer-portal session does not count as being signed in here.
     *
     * @param  Request  $request
     * @param  string|null  $guard  guard alias named on the route, if any
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (! Auth::guard($guard)->check()) {
            return $next($request);
        }

        $destination = PostLoginRedirect::sanitize($request->query('next'));

        return redirect($destination ?? RouteServiceProvider::HOME);
    }
}
