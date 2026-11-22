<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Application\Exceptions\SsoSignInRefused;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;

/**
 * Socialite provider for single sign-on over OpenID Connect: any OpenID
 * Connect identity provider with a userinfo endpoint, such as Keycloak,
 * Authentik, Authelia or Zitadel. It is set up with the provider's issuer,
 * whose discovery document lists the endpoints (OidcDiscovery), or with the
 * endpoint URLs themselves, instead of a package for each provider;
 * config/services.php has the settings.
 *
 * The endpoints are found when they are first needed, not when the provider
 * is built, so nothing asks the identity provider anything until a sign-in
 * starts. Problems on the way are thrown as SsoSignInRefused.
 */
class OidcProvider extends AbstractProvider
{
    /**
     * Seconds an ID token may be past its expiry, for clocks that disagree.
     */
    public const CLOCK_SKEW_SECONDS = 60;

    /**
     * The discovery document's names for the endpoints.
     */
    private const DISCOVERED_AS = [
        'authorize_url' => 'authorization_endpoint',
        'token_url' => 'token_endpoint',
        'userinfo_url' => 'userinfo_endpoint',
    ];

    /**
     * The settings that name each endpoint, for the log.
     */
    private const SETTINGS = [
        'authorize_url' => 'OIDC_AUTHORIZE_URL',
        'token_url' => 'OIDC_TOKEN_URL',
        'userinfo_url' => 'OIDC_USERINFO_URL',
    ];

    /**
     * OAuth 2.0 separates scopes with spaces.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The endpoint URLs that are set; an empty one comes from discovery.
     *
     * @var array{authorize_url: string, token_url: string, userinfo_url: string}
     */
    protected array $endpoints = [
        'authorize_url' => '',
        'token_url' => '',
        'userinfo_url' => '',
    ];

    /**
     * The issuer as configured, or empty when there is none.
     */
    protected string $issuer = '';

    /**
     * How the client authenticates at the token endpoint: "auto", "post" or
     * "basic" (OIDC_CLIENT_AUTH).
     */
    protected string $clientAuth = 'auto';

    protected ?OidcDiscovery $discovery = null;

    /**
     * Takes the issuer, the endpoint URLs and the client authentication from
     * the "oidc" settings in config/services.php.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config, OidcDiscovery $discovery): static
    {
        foreach (array_keys($this->endpoints) as $endpoint) {
            $this->endpoints[$endpoint] = trim((string) ($config[$endpoint] ?? ''));
        }

        $this->issuer = trim((string) ($config['issuer'] ?? ''));
        $this->clientAuth = strtolower(trim((string) ($config['client_auth'] ?? 'auto')));
        $this->discovery = $discovery;

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoint('authorize_url'), $state);
    }

    /**
     * An authorization endpoint may carry a query string of its own, such as
     * Azure AD B2C's `?p=<policy>`; the request's fields are added to it.
     *
     * @param  string  $url
     * @param  string|null  $state
     */
    protected function buildAuthUrlFromBase($url, $state): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoint('token_url');
    }

    /**
     * With client_secret_basic the client ID and secret travel in an HTTP
     * Basic Authorization header, each form-encoded first as RFC 6749
     * section 2.3.1 asks.
     *
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenHeaders($code): array
    {
        $headers = parent::getTokenHeaders($code);

        if ($this->clientAuthentication() === 'basic') {
            $headers['Authorization'] = 'Basic '.base64_encode(urlencode((string) $this->clientId).':'.urlencode((string) $this->clientSecret));
        }

        return $headers;
    }

    /**
     * With client_secret_basic the secret is in the header and must not be
     * sent in the body as well.
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    protected function getTokenFields($code): array
    {
        $fields = parent::getTokenFields($code);

        if ($this->clientAuthentication() === 'basic') {
            unset($fields['client_secret']);
        }

        return $fields;
    }

    /**
     * The claims the userinfo endpoint returns for the access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('userinfo_url'), [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $claims = json_decode((string) $response->getBody(), true);

        if (! is_array($claims) || array_is_list($claims)) {
            throw new SsoSignInRefused(
                'Sign-in with SSO refused: the userinfo endpoint did not answer with a JSON object. It may be sending a signed JWT, or the "openid" scope may be missing from OIDC_SCOPES.',
                SsoLogin::FAILED,
                ['content_type' => $response->getHeaderLine('Content-Type')],
            );
        }

        return $claims;
    }

    /**
     * The account the identity provider's answer is for: the userinfo
     * claims, and the claims of the ID token when the token endpoint sent one
     * (as the `id_token` attribute, after idTokenClaims checked them).
     *
     * @return User
     */
    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        try {
            $response = $this->getAccessTokenResponse($this->getCode());
        } catch (ConnectException $exception) {
            throw $this->unreachable('token', $exception);
        }

        if (! is_array($response)) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the token endpoint did not answer with a JSON object.');
        }

        try {
            $claims = $this->getUserByToken(Arr::get($response, 'access_token'));
        } catch (ConnectException $exception) {
            throw $this->unreachable('userinfo', $exception);
        }
        $idToken = isset($response['id_token']) ? $this->idTokenClaims($response['id_token'], $claims) : null;
        $user = $this->userInstance($response, $claims);

        return $user->map([...$user->attributes, 'id_token' => $idToken]);
    }

    /**
     * Maps the standard OpenID Connect claims; the account's ID is its "sub".
     *
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
            'nickname' => $user['preferred_username'] ?? null,
        ]);
    }

    /**
     * The identity provider could not be reached, or did not answer within
     * the timeouts in config/services.php (`oidc.guzzle`).
     *
     * @param  'token'|'userinfo'  $endpoint
     */
    private function unreachable(string $endpoint, ConnectException $exception): SsoSignInRefused
    {
        return SsoSignInRefused::misconfigured(
            "Sign-in with SSO failed: the identity provider's {$endpoint} endpoint did not answer in time, or could not be reached.",
            ['error' => $exception->getMessage()],
        );
    }

    /**
     * The claims of an ID token, once they pass the checks of OpenID Connect
     * Core 1.0 section 3.1.3.7 that apply: it is for this client, it has not
     * expired, it comes from the configured issuer (when there is one), and
     * it is about the account the userinfo endpoint described (section
     * 5.3.2).
     *
     * Its signature is not checked. The token came straight from the token
     * endpoint, over TLS, in answer to this client's own authenticated
     * request, and section 3.1.3.7 lets that stand in for the signature.
     *
     * @param  array<string, mixed>  $userinfo
     * @return array<string, mixed>
     */
    protected function idTokenClaims(mixed $idToken, array $userinfo): array
    {
        $claims = $this->decodeIdToken($idToken);

        if ($claims === null) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the ID token could not be decoded.');
        }

        $audience = $claims['aud'] ?? null;

        if (! in_array((string) $this->clientId, is_array($audience) ? $audience : [$audience], true)) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the ID token is for another client (its "aud" does not name OIDC_CLIENT_ID).', SsoLogin::FAILED, [
                'aud' => $audience,
            ]);
        }

        $expires = $claims['exp'] ?? null;

        if (! is_numeric($expires) || $expires + self::CLOCK_SKEW_SECONDS < Date::now()->getTimestamp()) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the ID token has expired, or has no "exp". Check the clocks of both servers.', SsoLogin::FAILED, [
                'exp' => $expires,
            ]);
        }

        $issuer = $claims['iss'] ?? null;

        if ($this->issuer !== '' && (! is_string($issuer)
            || OidcLogin::withoutTrailingSlash($issuer) !== OidcLogin::withoutTrailingSlash($this->issuer))) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the ID token comes from another issuer than OIDC_ISSUER.', SsoLogin::FAILED, [
                'iss' => $issuer,
                'expected' => $this->issuer,
            ]);
        }

        $subject = $claims['sub'] ?? null;
        $userinfoSubject = $userinfo['sub'] ?? null;

        if (! is_scalar($subject) || ! is_scalar($userinfoSubject) || (string) $subject !== (string) $userinfoSubject) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the ID token and the userinfo endpoint describe different accounts ("sub").', SsoLogin::FAILED, [
                'id_token_sub' => $subject,
                'userinfo_sub' => $userinfoSubject,
            ]);
        }

        return $claims;
    }

    /**
     * The payload of a signed JWT (header.payload.signature), or null when
     * it is not one, for example an encrypted ID token.
     *
     * @return array<string, mixed>|null
     */
    private function decodeIdToken(mixed $idToken): ?array
    {
        if (! is_string($idToken)) {
            return null;
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $payload === false ? null : json_decode($payload, true);

        return is_array($claims) && $claims !== [] && ! array_is_list($claims) ? $claims : null;
    }

    /**
     * The endpoint's URL: the one that is set, or else the one the issuer's
     * discovery document lists.
     *
     * @param  'authorize_url'|'token_url'|'userinfo_url'  $endpoint
     */
    protected function endpoint(string $endpoint): string
    {
        if ($this->endpoints[$endpoint] !== '') {
            return $this->endpoints[$endpoint];
        }

        $url = $this->discovered()[self::DISCOVERED_AS[$endpoint]] ?? null;

        if (! is_string($url) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw SsoSignInRefused::misconfigured(
                'Sign-in with SSO failed: the identity provider\'s discovery document has no '.self::DISCOVERED_AS[$endpoint].'. Set '.self::SETTINGS[$endpoint].'.',
                ['issuer' => $this->issuer],
            );
        }

        return $url;
    }

    /**
     * "post" (client_secret_post, the secret in the request body) or "basic"
     * (client_secret_basic, in an Authorization header). OIDC_CLIENT_AUTH
     * may name one. Left at "auto", it is post, unless the issuer's discovery
     * document lists the methods its token endpoint takes and that list has
     * basic but not post.
     */
    protected function clientAuthentication(): string
    {
        if (in_array($this->clientAuth, ['post', 'basic'], true)) {
            return $this->clientAuth;
        }

        if ($this->issuer === '') {
            return 'post';
        }

        $methods = $this->discovered()['token_endpoint_auth_methods_supported'] ?? null;

        return is_array($methods)
            && ! in_array('client_secret_post', $methods, true)
            && in_array('client_secret_basic', $methods, true) ? 'basic' : 'post';
    }

    /**
     * The issuer's discovery document.
     *
     * @return array<string, mixed>
     */
    protected function discovered(): array
    {
        if ($this->issuer === '' || $this->discovery === null) {
            throw SsoSignInRefused::misconfigured('Sign-in with SSO failed: an endpoint URL is not set and there is no OIDC_ISSUER to discover it from.');
        }

        return $this->discovery->document($this->issuer, $this->getHttpClient());
    }
}
