<?php

namespace App\Rules;

use App\Support\Net\PrivateNetworkGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a host the server would connect to when it is a private, loopback,
 * link-local or otherwise non-publicly-routable address: the SSRF guard for
 * connection targets that are not HTTP URLs, such as an SMTP host or an SMTP
 * DSN.
 *
 * Accepts a bare hostname, an IP literal (IPv6 with or without brackets) or a
 * URL of any scheme, whose host is checked. Empty values pass so the rule
 * composes with `nullable`. Like PublicHttpUrl, a hostname that does not
 * resolve is allowed: a connection to it cannot reach a private network.
 *
 * Hosts in $allowedHosts are exempt: private hosts an operator has named as
 * trusted (for mail, MAIL_ALLOWED_PRIVATE_HOSTS). Matching is on the exact
 * host, case-insensitive.
 */
class PublicHost implements ValidationRule
{
    /**
     * @param  list<string>  $allowedHosts
     */
    public function __construct(private readonly array $allowedHosts = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $host = self::hostOf($value);

        if ($host === null) {
            $fail('The :attribute must name a host.');

            return;
        }

        if (! self::isNamed($host, $this->allowedHosts) && self::isBlocked($host)) {
            $fail('The :attribute must be a publicly reachable host, not a private or reserved address.');
        }
    }

    /**
     * Whether connecting to the host would reach a private or reserved address.
     */
    public static function isBlocked(string $host): bool
    {
        $literal = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? "[{$host}]" : $host;

        return PrivateNetworkGuard::blockedReason('https://'.$literal) !== null;
    }

    /**
     * Whether the host is one of the named, trusted ones.
     *
     * @param  list<string>  $allowedHosts
     */
    public static function isNamed(string $host, array $allowedHosts): bool
    {
        return in_array(strtolower(trim($host, '[]')), $allowedHosts, true);
    }

    /**
     * The host a value names: the host part of a URL, or the value itself.
     */
    public static function hostOf(string $value): ?string
    {
        $value = trim($value);

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? trim($host, '[]') : null;
        }

        return trim($value, '[]');
    }
}
