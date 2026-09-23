<?php

namespace Tests\Support;

use App\Domains\Accounts\Application\OAuth\OAuthServer;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

/**
 * Helpers for tests that drive the OAuth server end to end.
 */
final class OAuthTesting
{
    /** @var array{private: string, public: string}|null */
    private static ?array $keys = null;

    public const REDIRECT_URI = 'https://client.example/callback';

    /**
     * Give Passport a signing key pair through configuration, generated once
     * per process so the suite does not pay for RSA generation per test.
     */
    public static function useKeys(): void
    {
        if (self::$keys === null) {
            $key = RSA::createKey(2048);

            self::$keys = [
                'private' => (string) $key,
                'public' => (string) $key->getPublicKey(),
            ];
        }

        config([
            'passport.private_key' => self::$keys['private'],
            'passport.public_key' => self::$keys['public'],
        ]);
    }

    /**
     * Switch the OAuth server on through a consumer that is always enabled.
     */
    public static function enableServer(): void
    {
        app(OAuthServer::class)->consumer('tests', fn () => true);
    }

    /**
     * A public client for the authorization code grant, the kind dynamic
     * client registration creates.
     */
    public static function publicClient(string $name = 'Test client'): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: [self::REDIRECT_URI],
            confidential: false,
        );
    }

    /**
     * Run the authorization code grant with PKCE for a signed-in user and
     * return the token endpoint's JSON: consent page, approval, then the
     * code exchange.
     *
     * @return array<string, mixed>
     */
    public static function authorize(TestCase $test, User $user, Client $client, string $scope = ''): array
    {
        $verifier = Str::random(64);
        $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

        $test->actingAs($user, 'web');

        $test->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'state-value',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))->assertOk();

        $approval = $test->post('/oauth/authorize', [
            'state' => 'state-value',
            'client_id' => $client->getKey(),
            'auth_token' => session('authToken'),
        ]);

        $approval->assertRedirect();
        parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $query);

        $response = $test->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $query['code'] ?? null,
        ]);

        $response->assertOk();

        app('auth')->forgetGuards();

        return $response->json();
    }
}
