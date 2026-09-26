<?php

namespace App\Platform\Operations\Managed;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module install and uninstall: open everywhere, except on a managed install
 * whose provider gives it no writable Modules directory. Route alias
 * `modules-installable`. Only official, signed marketplace releases can be
 * installed at all, and pairing stays behind `not-managed`.
 */
class EnsureModulesInstallable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ManagedMode::enabled() && ! ManagedMode::modulesInstallable()) {
            return response()->json([
                'error' => 'managed_mode',
                'message' => 'Your hosting provider manages modules.',
            ], 403);
        }

        return $next($request);
    }
}
