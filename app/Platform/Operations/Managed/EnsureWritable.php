<?php

namespace App\Platform\Operations\Managed;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Business writes stop at the boundary. Authentication and reading remain available. */
class EnsureWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ManagedMode::readOnly() || $this->allowed($request)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'read_only',
            'message' => 'This instance is read-only. You can still view and download your documents.',
        ], Response::HTTP_FORBIDDEN);
    }

    private function allowed(Request $request): bool
    {
        $path = trim($request->path(), '/');
        // JSON-RPC transports carry reads and writes over POST; each MCP write tool guards itself.
        if ($path === 'mcp') {
            return true;
        }
        if ($request->isMethodSafe()) {
            return ! preg_match('#^invitations/[^/]+/decline$#', $path)
                && ! str_contains($request->route()?->getActionName() ?? '', 'AcceptEstimateController');
        }
        if ($request->isMethod('POST') && in_array($path, [
            'login', 'auth/logout', 'api/v1/auth/login', 'api/v1/auth/logout',
            'api/v1/auth/password/email', 'api/v1/auth/reset/password',
            'oauth/token', 'oauth/authorize', 'oauth/revoke', 'oauth/register',
        ], true)) {
            return true;
        }
        if ($request->isMethod('POST') && preg_match('#^(?:[^/]+/customer/(?:login|logout)|api/v1/[^/]+/customer/auth/(?:password/email|reset/password))$#', $path)) {
            return true;
        }
        if ($request->isMethod('DELETE') && preg_match('#^api/v1/auth/tokens/[^/]+$#', $path)) {
            return true; // the controller can revoke only the caller's own devices
        }
        if ($path === 'api/v1/me' && $request->isMethod('PUT')) {
            return $this->ownPassword($request, $request->user());
        }
        if ($request->isMethod('POST') && preg_match('#^api/v1/[^/]+/customer/profile$#', $path)) {
            return $this->ownPassword($request, $request->user('customer'));
        }

        return false;
    }

    private function ownPassword(Request $request, mixed $user): bool
    {
        if (! $user || ! is_string($request->input('password')) || ! $request->filled('password') || $request->allFiles() !== []) {
            return false;
        }
        if (array_diff(array_keys($request->all()), ['password', 'password_confirmation', 'confirm_password', 'name', 'email', '_token']) !== []) {
            return false;
        }
        foreach (['name', 'email'] as $field) {
            if ($request->has($field) && $request->input($field) !== $user->$field) {
                return false;
            }
        }

        return true;
    }
}
