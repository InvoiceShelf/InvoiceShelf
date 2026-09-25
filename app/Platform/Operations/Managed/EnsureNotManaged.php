<?php

namespace App\Platform\Operations\Managed;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses routes a hosting provider owns on a managed install: storage and
 * backups, PDF rendering and fonts, the server's mail transport, and module
 * installation. Route alias `not-managed`.
 */
class EnsureNotManaged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ManagedMode::enabled()) {
            return response()->json([
                'error' => 'managed_mode',
                'message' => 'Your hosting provider manages this setting.',
            ], 403);
        }

        return $next($request);
    }
}
