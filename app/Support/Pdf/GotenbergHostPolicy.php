<?php

namespace App\Support\Pdf;

use App\Support\Net\PrivateNetworkGuard;

/**
 * Decides whether a Gotenberg host is exempt from {@see PrivateNetworkGuard}.
 *
 * Gotenberg is normally deployed as a sidecar on a private network — the shipped
 * default host is `http://pdf:3000` — which the SSRF guard rejects. The exemption
 * is declared in the environment and names the single host it trusts:
 *
 *     GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000
 *
 * It is deliberately NOT a boolean and deliberately not settable from the admin UI.
 * `gotenberg_host` itself stays editable by any super admin, and the driver returns
 * the upstream response body verbatim as the PDF — so a blanket "allow private"
 * switch would let that setting be repointed at a link-local metadata endpoint and
 * read back the response. Matching one declared host keeps the sidecar working while
 * every other private target stays blocked.
 *
 * Both the save-time validation rule and the runtime driver guard call this, so the
 * two layers cannot drift apart.
 */
class GotenbergHostPolicy
{
    /**
     * Whether the given host is the operator-declared Gotenberg host, and may
     * therefore skip the private-network check.
     */
    public static function isExemptFromPrivateNetworkGuard(?string $host): bool
    {
        $allowed = config('pdf.connections.gotenberg.allowed_private_host');

        if (! is_string($allowed) || ! is_string($host)) {
            return false;
        }

        $allowed = self::normalize($allowed);
        $host = self::normalize($host);

        // An unset or unparseable allowlist never exempts anything.
        return $allowed !== null && $allowed === $host;
    }

    /**
     * Reduce a URL to scheme://host[:port][/path] with casing and any trailing
     * slash removed, so `HTTP://PDF:3000/` and `http://pdf:3000` compare equal.
     *
     * Returns null when the value is empty or carries no scheme and host.
     */
    private static function normalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        // parse_url keeps IPv6 literals bracketed; strip them on both sides so the
        // comparison is consistent.
        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === '') {
            return null;
        }

        return sprintf(
            '%s://%s%s%s',
            strtolower($parts['scheme']),
            $host,
            isset($parts['port']) ? ':'.$parts['port'] : '',
            rtrim($parts['path'] ?? '', '/'),
        );
    }
}
