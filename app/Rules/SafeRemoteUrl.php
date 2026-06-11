<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeRemoteUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value)) {
            $fail('The :attribute must be a valid, publicly reachable http(s) URL.');
        }
    }

    /**
     * A URL is safe when it is http(s) and every IP its host resolves to is a
     * public address. This blocks SSRF to loopback, private, link-local
     * (including the cloud metadata endpoint 169.254.169.254) and reserved
     * ranges. Literal-IP hosts are checked directly.
     */
    public static function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]');

        $ips = self::resolve($host);

        // Unresolvable host — reject rather than risk the request.
        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $resolved = gethostbynamel($host);
            if ($resolved !== false) {
                $ips = $resolved;
            }
        }

        return $ips;
    }

    protected static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            $blockedRanges = [
                ['127.0.0.0', '127.255.255.255'],   // loopback
                ['169.254.0.0', '169.254.255.255'], // link-local + cloud metadata
                ['100.64.0.0', '100.127.255.255'],  // CGNAT
                ['0.0.0.0', '0.255.255.255'],        // "this" network
            ];

            foreach ($blockedRanges as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return false;
                }
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $lower = strtolower($ip);
            if ($lower === '::1'
                || str_starts_with($lower, 'fe80')
                || str_starts_with($lower, 'fc')
                || str_starts_with($lower, 'fd')) {
                return false;
            }
        }

        return true;
    }
}
