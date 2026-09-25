<?php

namespace App\Support\Urls;

/**
 * Where customers reach the install: the customer portal's own host when
 * CUSTOMER_PORTAL_URL names one apart from the app's, the app's address
 * otherwise.
 *
 * Every link sent to a customer is built here, so giving the portal a host of
 * its own moves all of them at once.
 */
final class CustomerUrl
{
    /**
     * Whether the portal has a host of its own, apart from the app's.
     */
    public static function separate(): bool
    {
        $portal = self::configuredUrl();

        return $portal !== null && self::hostOf($portal) !== self::hostOf((string) config('app.url'));
    }

    /**
     * The portal's own address without a trailing slash, or null when it
     * lives on the app host.
     */
    public static function portalUrl(): ?string
    {
        return self::separate() ? self::configuredUrl() : null;
    }

    /**
     * The customer-side address of an app path.
     */
    public static function to(string $path): string
    {
        $portal = self::portalUrl();

        return $portal === null ? url($path) : $portal.'/'.ltrim($path, '/');
    }

    /**
     * The customer-side address of a named route.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        $portal = self::portalUrl();

        return $portal === null ? route($name, $parameters) : $portal.route($name, $parameters, false);
    }

    /**
     * Every host that serves the portal, lower case; none when it lives on
     * the app host.
     *
     * @return list<string>
     */
    public static function hosts(): array
    {
        $portal = self::portalUrl();

        if ($portal === null) {
            return [];
        }

        $listed = array_filter(array_map(
            fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) config('invoiceshelf.customer_portal.hosts')),
        ));

        return array_values(array_unique($listed ?: [self::hostOf($portal)]));
    }

    public static function isPortalHost(string $host): bool
    {
        return in_array(strtolower($host), self::hosts(), true);
    }

    /**
     * Let the portal hosts keep a session: Sanctum only starts one for a
     * request from a stateful domain.
     */
    public static function trustPortalHosts(): void
    {
        $portal = self::portalUrl();

        if ($portal === null) {
            return;
        }

        $port = parse_url($portal, PHP_URL_PORT);
        $domains = [...self::hosts(), self::hostOf($portal).($port ? ':'.$port : '')];

        config(['sanctum.stateful' => array_values(array_unique([
            ...(array) config('sanctum.stateful', []),
            ...$domains,
        ]))]);
    }

    private static function configuredUrl(): ?string
    {
        $url = rtrim(trim((string) config('invoiceshelf.customer_portal.url')), '/');

        return $url === '' ? null : $url;
    }

    private static function hostOf(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
