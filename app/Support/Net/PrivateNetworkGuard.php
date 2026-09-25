<?php

namespace App\Support\Net;

/**
 * Decides whether a URL is safe for the server to request — i.e. that it does
 * not target a private, loopback, link-local, or otherwise non-publicly-routable
 * address.
 *
 * This is the single source of truth behind two layers:
 *   - the `PublicHttpUrl` validation rule (save-time UX gate), and
 *   - runtime guards in outbound HTTP drivers (OpenRouter AI base URL, the
 *     CurrencyConverter "DEDICATED" exchange-rate URL, and FileDisk S3/Spaces
 *     endpoints) that re-check right before the request is made.
 *
 * Threat model: SSRF where an admin or per-company owner supplies a base URL or
 * endpoint that the server then fetches with credentials (bearer token, S3 keys)
 * attached. We block IP literals in unsafe ranges and hostnames that resolve into
 * them. A host that does NOT resolve is treated as "not provably unsafe"
 * (fail-open): a request to an unresolvable host cannot reach a private network,
 * and failing open keeps offline validation from rejecting legitimate public
 * hostnames. The authoritative enforcement is the runtime guard, which re-checks
 * at request time.
 *
 * Known limitation: this is not TOCTOU/DNS-rebinding-proof. A fully hardened
 * implementation would pin the connection to the validated IP (cURL
 * CURLOPT_RESOLVE). That is intentionally out of scope here — the realistic
 * finding is direct private targeting, which this fully covers.
 */
class PrivateNetworkGuard
{
    /**
     * IPv4 CIDR blocks that must never be the target of a server-side request.
     *
     * @var array<int, string>
     */
    private const BLOCKED_IPV4 = [
        '0.0.0.0/8',        // "this" network / unspecified
        '10.0.0.0/8',       // RFC1918 private
        '100.64.0.0/10',    // RFC6598 carrier-grade NAT
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local (incl. cloud metadata 169.254.169.254)
        '172.16.0.0/12',    // RFC1918 private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // TEST-NET-1
        '192.168.0.0/16',   // RFC1918 private
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '240.0.0.0/4',      // reserved (incl. 255.255.255.255 broadcast)
    ];

    /**
     * IPv6 CIDR blocks that must never be the target of a server-side request.
     *
     * IPv4-mapped addresses (::ffff:0:0/96) are handled explicitly by unwrapping
     * the embedded IPv4 in {@see self::ipIsBlocked()}.
     *
     * @var array<int, string>
     */
    private const BLOCKED_IPV6 = [
        '::1/128',      // loopback
        '::/128',       // unspecified
        'fc00::/7',     // unique local address
        'fe80::/10',    // link-local
        '64:ff9b::/96', // NAT64 (embeds IPv4)
        '2001:db8::/32', // documentation
    ];

    /**
     * Return a human-readable reason when the URL must NOT be requested, or null
     * when it is allowed (including the fail-open unresolvable-host case).
     */
    public static function blockedReason(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme']) || ! isset($parts['host'])) {
            return 'URL must include a scheme and host';
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'URL scheme must be http or https';
        }

        // parse_url keeps IPv6 literals wrapped in brackets; strip them.
        $host = trim($parts['host'], '[]');
        if ($host === '') {
            return 'URL must include a host';
        }

        // IP literal — check directly without DNS.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::ipIsBlocked($host)
                ? "URL host {$host} is a private or reserved address"
                : null;
        }

        // Hostname — resolve and check every address it points at.
        $ips = self::resolveHost($host);
        if ($ips === []) {
            // Unresolvable: not provably unsafe. The actual request will fail.
            return null;
        }

        foreach ($ips as $ip) {
            if (self::ipIsBlocked($ip)) {
                return "URL host {$host} resolves to a private or reserved address ({$ip})";
            }
        }

        return null;
    }

    /**
     * Whether an operator has named this target as a trusted private host for
     * one feature (config/network.php, allowed_private_hosts.<feature>).
     *
     * An entry with a scheme matches the exact URL, ignoring case and a
     * trailing slash; a bare entry matches the target's host, whether the
     * target is a bare host or a URL. An exemption for one feature never
     * applies to another.
     */
    public static function isExempt(string $feature, ?string $target): bool
    {
        if (! is_string($target) || trim($target) === '') {
            return false;
        }

        foreach ((array) config("network.allowed_private_hosts.{$feature}", []) as $allowed) {
            if (! is_string($allowed) || trim($allowed) === '') {
                continue;
            }

            $matches = str_contains($allowed, '://')
                ? self::normalizedUrl($allowed) !== null && self::normalizedUrl($allowed) === self::normalizedUrl($target)
                : self::hostOf($target) === strtolower(trim($allowed, " \t[]"));

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * The host a target names: the host part of a URL, or the value itself,
     * lowercased and without IPv6 brackets. Null when a URL carries no host.
     */
    public static function hostOf(string $target): ?string
    {
        $target = trim($target);

        if (str_contains($target, '://')) {
            $host = parse_url($target, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? strtolower(trim($host, '[]')) : null;
        }

        return strtolower(trim($target, '[]'));
    }

    /**
     * A URL reduced to scheme://host[:port][/path], lowercased, without a
     * trailing slash; null when it has no scheme and host.
     */
    private static function normalizedUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

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

    /**
     * Throw when the URL is not safe to request. Used by runtime driver guards.
     *
     * @throws BlockedUrlException
     */
    public static function assertAllowed(string $url): void
    {
        $reason = self::blockedReason($url);

        if ($reason !== null) {
            throw new BlockedUrlException($reason);
        }
    }

    /**
     * Whether a single IP address falls inside any blocked range.
     */
    public static function ipIsBlocked(string $ip): bool
    {
        // Normalise IPv4-mapped IPv6 (e.g. ::ffff:10.0.0.1) to its embedded IPv4.
        $mapped = self::extractMappedIpv4($ip);
        if ($mapped !== null) {
            return self::ipIsBlocked($mapped);
        }

        $isIpv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $blocks = $isIpv4 ? self::BLOCKED_IPV4 : self::BLOCKED_IPV6;

        foreach ($blocks as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an IP (v4 or v6) is contained in the given CIDR block.
     */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton((string) $subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Different address families (v4 vs v6) can never match.
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $wholeBytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($wholeBytes > 0 && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainderBits > 0) {
            $mask = (~((1 << (8 - $remainderBits)) - 1)) & 0xFF;

            if ((ord($ipBin[$wholeBytes]) & $mask) !== (ord($subnetBin[$wholeBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a hostname to all of its A and AAAA addresses.
     *
     * @return array<int, string>
     */
    private static function resolveHost(string $host): array
    {
        $ips = [];

        if (function_exists('gethostbynamel')) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Extract the embedded IPv4 from an IPv4-mapped IPv6 address (::ffff:0:0/96),
     * or null if the value is not such an address.
     */
    private static function extractMappedIpv4(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return null;
        }

        // ::ffff:0:0/96 = 10 zero bytes followed by 0xff 0xff.
        $prefix = str_repeat("\x00", 10)."\xff\xff";
        if (strncmp($bin, $prefix, 12) !== 0) {
            return null;
        }

        $ipv4 = inet_ntop(substr($bin, 12, 4));

        return $ipv4 === false ? null : $ipv4;
    }
}
