<?php

namespace App\Domains\Accounts\Application;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Socialite provider for sign-in with SSO: any identity provider that speaks
 * OAuth 2.0 and has an OpenID Connect userinfo endpoint, such as Authentik,
 * Keycloak, Authelia or Zitadel. It is set up with the provider's endpoint
 * URLs instead of a package for each provider; config/services.php has the
 * settings.
 */
class OAuth2Provider extends AbstractProvider
{
    /**
     * OAuth 2.0 separates scopes with spaces.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The identity provider's authorization, token and userinfo endpoints.
     *
     * @var array{authorize_url: string, token_url: string, userinfo_url: string}
     */
    protected array $endpoints = [
        'authorize_url' => '',
        'token_url' => '',
        'userinfo_url' => '',
    ];

    /**
     * Takes the endpoint URLs from the "oauth2" settings in
     * config/services.php.
     *
     * @param  array<string, mixed>  $config
     */
    public function setEndpoints(array $config): static
    {
        foreach (array_keys($this->endpoints) as $endpoint) {
            $this->endpoints[$endpoint] = (string) ($config[$endpoint] ?? '');
        }

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoints['authorize_url'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoints['token_url'];
    }

    /**
     * The claims the userinfo endpoint returns for the access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoints['userinfo_url'], [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $claims = json_decode((string) $response->getBody(), true);

        return is_array($claims) ? $claims : [];
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
}
