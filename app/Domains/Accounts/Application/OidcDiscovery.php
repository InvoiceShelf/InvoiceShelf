<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Application\Exceptions\SsoSignInRefused;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;

/**
 * The identity provider's OpenID Connect discovery document, read from
 * `{issuer}/.well-known/openid-configuration` when OIDC_ISSUER is set. It
 * lists the endpoints a sign-in needs, and how the token endpoint wants the
 * client to authenticate.
 *
 * A document is kept in the cache for an hour once it passed the checks. One
 * that could not be read, or did not pass, is never kept: the next sign-in
 * asks again.
 */
class OidcDiscovery
{
    /**
     * Seconds a document is kept.
     */
    public const CACHE_SECONDS = 3600;

    /**
     * Seconds to wait for the identity provider. A sign-in is waiting too.
     */
    public const TIMEOUT_SECONDS = 5;

    /**
     * The issuer's discovery document, fetched with the HTTP client the
     * provider uses for its other requests.
     *
     * @return array<string, mixed>
     *
     * @throws SsoSignInRefused when the document cannot be read, or names
     *                          another issuer
     */
    public function document(string $issuer, ClientInterface $http): array
    {
        $key = 'oidc_discovery:'.hash('sha256', OidcLogin::withoutTrailingSlash($issuer));
        $document = Cache::get($key);

        if (is_array($document)) {
            return $document;
        }

        $document = $this->fetch($issuer, $http);
        Cache::put($key, $document, self::CACHE_SECONDS);

        return $document;
    }

    /**
     * Reads the document and checks it is the issuer's own: OpenID Connect
     * Discovery 1.0 section 4.3 wants its `issuer` to be exactly the one
     * asked for. A trailing slash on either side is not held against it.
     *
     * @return array<string, mixed>
     */
    private function fetch(string $issuer, ClientInterface $http): array
    {
        // Discovery section 4.1: drop the issuer's terminating slash before
        // appending the well-known path.
        $url = OidcLogin::withoutTrailingSlash($issuer).'/.well-known/openid-configuration';

        try {
            $response = $http->request('GET', $url, [
                RequestOptions::HEADERS => ['Accept' => 'application/json'],
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
                RequestOptions::CONNECT_TIMEOUT => self::TIMEOUT_SECONDS,
            ]);
        } catch (GuzzleException $exception) {
            throw SsoSignInRefused::misconfigured(
                'Sign-in with SSO failed: the identity provider\'s discovery document could not be read. Check OIDC_ISSUER and that this server can reach it.',
                ['url' => $url, 'error' => $exception->getMessage()],
            );
        }

        $document = json_decode((string) $response->getBody(), true);

        if (! is_array($document) || ! is_string($document['issuer'] ?? null)) {
            throw SsoSignInRefused::misconfigured(
                'Sign-in with SSO failed: the identity provider\'s discovery document is not a JSON object with an "issuer". Check OIDC_ISSUER.',
                ['url' => $url],
            );
        }

        if (OidcLogin::withoutTrailingSlash($document['issuer']) !== OidcLogin::withoutTrailingSlash($issuer)) {
            throw SsoSignInRefused::misconfigured(
                'Sign-in with SSO failed: the issuer in the identity provider\'s discovery document does not match OIDC_ISSUER.',
                ['url' => $url, 'expected' => $issuer, 'issuer' => $document['issuer']],
            );
        }

        return $document;
    }
}
