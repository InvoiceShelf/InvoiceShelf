<?php

namespace App\Domains\Accounts\Application;

use Illuminate\Http\Request;

/**
 * Where a user goes once they have signed in.
 *
 * Every way of signing in carries the destination as a `next` path and passes
 * it through here: today the password form, later an SSO callback and a
 * second-factor step. Only a path on this origin is accepted, so a crafted
 * link can never turn the sign-in page into an open redirect.
 *
 * Most destinations are SPA routes, which the login page opens with the
 * client-side router. A few are pages the server renders itself, such as the
 * OAuth consent screen; the login page must load those with a full navigation,
 * so they are listed here and mirrored in LoginView.vue.
 */
final class PostLoginRedirect
{
    /**
     * Server-rendered pages a sign-in may return to.
     *
     * Keep in step with SERVER_REDIRECT_PATHS in
     * resources/scripts/features/auth/views/LoginView.vue.
     */
    public const SERVER_PATHS = ['/oauth/authorize'];

    /**
     * The given `next` value when it is safe to send a signed-in user to,
     * otherwise null.
     */
    public static function sanitize(mixed $next): ?string
    {
        if (! is_string($next) || $next === '' || strlen($next) > 4096) {
            return null;
        }

        // A path on this origin: not protocol-relative, no backslash (which
        // browsers read as a slash), no control characters.
        if ($next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $next) === 1) {
            return null;
        }

        $parts = parse_url($next);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return $next;
    }

    /**
     * Whether the path is one of the server-rendered pages in SERVER_PATHS.
     */
    public static function isServerPath(string $path): bool
    {
        foreach (self::SERVER_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'?')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The sign-in page for a guest who asked for the given request.
     *
     * A guest who opened a server-rendered page from SERVER_PATHS is sent
     * back to it afterwards. Everything else keeps the plain sign-in page:
     * SPA routes handle their own return trip.
     */
    public static function loginUrlFor(Request $request): string
    {
        $query = $request->getQueryString();
        $path = $request->getPathInfo().($query !== null ? '?'.$query : '');

        if ($request->isMethod('GET') && ! $request->expectsJson()
            && self::isServerPath($path) && self::sanitize($path) !== null) {
            return route('login', ['next' => $path]);
        }

        return route('login');
    }
}
