<?php

use App\Domains\Accounts\Application\SsoHandoffs;
use App\Domains\Accounts\Application\SsoLogin;
use App\Domains\Accounts\Models\SsoIdentity;
use App\Domains\Accounts\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as OidcAccount;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/** Turns single sign-on on, over OpenID Connect, pointed at a made-up identity provider. */
function configureOidc(array $settings = []): void
{
    config(['services.oidc' => [
        'client_id' => 'invoiceshelf',
        'client_secret' => 'client-secret',
        'issuer' => null,
        'authorize_url' => 'https://sso.test/authorize',
        'token_url' => 'https://sso.test/token',
        'userinfo_url' => 'https://sso.test/userinfo',
        'client_auth' => 'auto',
        'require_verified_email' => true,
        'scopes' => 'openid profile email',
        'redirect' => '/auth/oidc/callback',
        ...$settings,
    ]]);
}

/** Settings that leave the three endpoint URLs to discovery from the issuer. */
function oidcIssuerSettings(array $settings = []): array
{
    return [
        'issuer' => 'https://sso.test/realms/acme',
        'authorize_url' => null,
        'token_url' => null,
        'userinfo_url' => null,
        ...$settings,
    ];
}

/** The HTTP client options config/services.php ships for the identity provider. */
function oidcShippedGuzzle(): array
{
    return (require config_path('services.php'))['oidc']['guzzle'] ?? [];
}

/**
 * The identity provider answers the next requests with these, in order,
 * through an HTTP client with the shipped options. Returns the requests it
 * got, as they arrive.
 */
function oidcIdentityProvider(array $answers, array $settings = []): ArrayObject
{
    $requests = new ArrayObject;
    $handler = HandlerStack::create(new MockHandler($answers));
    $handler->push(Middleware::history($requests));
    configureOidc(['guzzle' => [...oidcShippedGuzzle(), 'handler' => $handler], ...$settings]);

    return $requests;
}

/** A JSON answer from the identity provider. */
function oidcJson(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
}

/** The discovery document of the made-up issuer https://sso.test/realms/acme. */
function oidcDiscovery(array $overrides = []): array
{
    return [
        'issuer' => 'https://sso.test/realms/acme',
        'authorization_endpoint' => 'https://sso.test/realms/acme/protocol/openid-connect/auth',
        'token_endpoint' => 'https://sso.test/realms/acme/protocol/openid-connect/token',
        'userinfo_endpoint' => 'https://sso.test/realms/acme/protocol/openid-connect/userinfo',
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
        ...$overrides,
    ];
}

/** The token endpoint's answer, with an ID token when one is given. */
function oidcTokenAnswer(?string $idToken = null): Response
{
    return oidcJson(array_filter([
        'access_token' => 'the-access-token',
        'token_type' => 'Bearer',
        'expires_in' => 300,
        'id_token' => $idToken,
    ]));
}

/** The userinfo endpoint's answer for the seeded admin's account; a null claim is left out. */
function oidcUserinfoAnswer(array $claims = []): Response
{
    return oidcJson(array_filter([
        'sub' => 'sso-account-1',
        'email' => seededAdmin()->email,
        'email_verified' => true,
        ...$claims,
    ], fn (mixed $claim): bool => $claim !== null));
}

/**
 * An ID token as a provider would send it. Its signature is made up: the app
 * does not check it (see OidcProvider::idTokenClaims). A null claim is left
 * out.
 */
function oidcIdToken(array $claims = []): string
{
    $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

    return $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode(array_filter([
        'iss' => 'https://sso.test/realms/acme',
        'aud' => 'invoiceshelf',
        'sub' => 'sso-account-1',
        'exp' => time() + 300,
        'iat' => time(),
        ...$claims,
    ], fn (mixed $claim): bool => $claim !== null)).'.c2lnbmF0dXJl';
}

/**
 * The identity provider answers the callback with this account: its
 * userinfo claims (email_verified is true unless the claims say otherwise)
 * and the claims of its ID token, if it sent one.
 */
function signInAs(string $id, ?string $email, array $claims = [], ?array $idToken = null): void
{
    Socialite::fake('oidc', (new OidcAccount)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => true, ...$claims])
        ->map(['id' => $id, 'email' => $email, 'id_token' => $idToken]));
}

/** The callback the identity provider sends the browser back to. */
function oidcCallback(): TestResponse
{
    return get('/auth/oidc/callback?code=the-code&state=the-state');
}

/** The callback, with the state and code verifier the redirect left in the session. */
function oidcCompleteSignIn(): TestResponse
{
    test()->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);

    return oidcCallback();
}

/** What the Blade shell passes to the SPA about single sign-on. */
function ssoOnLoginPage(): ?array
{
    test()->withoutVite();

    if (! preg_match('/window\.sso_login = (\{.*?\});/', view('app')->render(), $match)) {
        return null;
    }

    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}

/** The query of a redirect's Location. */
function ssoQueryOf(TestResponse $response): array
{
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query;
}

/** The log got a line at this level that says this. */
function expectSsoLogged(string $level, string $text): void
{
    Log::shouldHaveReceived('log')
        ->withArgs(fn (string $logged, string $message): bool => $logged === $level && str_contains($message, $text))
        ->atLeast()->once();
}

/** The user's OpenID Connect identity, if they have one. */
function oidcIdentityOf(User $user): ?SsoIdentity
{
    return $user->ssoIdentities()->where('type', SsoIdentity::TYPE_OIDC)->first();
}

/** Links the user to an account at the made-up identity provider. */
function linkOidc(User $user, string $subject = 'sso-account-1', string $issuer = 'https://sso.test'): SsoIdentity
{
    return SsoIdentity::factory()->for($user)->create(['issuer' => $issuer, 'subject' => $subject]);
}

/**
 * Another sign-in links this account just before this one inserts its link,
 * after this one's checks found none: the first transaction this request
 * starts, the insert's own, starts after the other request committed.
 */
function oidcRaceToLink(array $identity): void
{
    $raced = false;

    DB::connection()->beforeStartingTransaction(function () use (&$raced, $identity): void {
        if (! $raced) {
            $raced = true;
            DB::table('sso_identities')->insert([...$identity, 'type' => SsoIdentity::TYPE_OIDC, 'created_at' => now(), 'updated_at' => now()]);
        }
    });
}

function seededAdmin(): User
{
    return User::query()->where('role', 'super admin')->orderBy('id')->firstOrFail();
}

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    configureOidc();
    Log::spy();
});

test('single sign-on is off until the client and the three endpoint URLs are set', function (string $setting) {
    configureOidc([$setting => null]);

    get('/auth/sso/redirect')->assertNotFound();
    oidcCallback()->assertNotFound();

    expect(ssoOnLoginPage())->toBeNull();
})->with(['client_id', 'client_secret', 'authorize_url', 'token_url', 'userinfo_url']);

test('with an issuer, the client ID and secret are all single sign-on needs', function () {
    configureOidc(oidcIssuerSettings());

    expect(ssoOnLoginPage())->toBe(['url' => '/auth/sso/redirect', 'error' => null])
        ->and(getJson('/api/v1/app/client-manifest')->json('sso_login'))->toBeTrue();

    configureOidc(oidcIssuerSettings(['client_secret' => null]));

    expect(ssoOnLoginPage())->toBeNull();
});

test('the login page offers single sign-on', function () {
    expect(ssoOnLoginPage())->toBe([
        'url' => '/auth/sso/redirect',
        'error' => null,
    ]);
});

test('the redirect asks the identity provider for an authorization code with PKCE and the configured scopes', function () {
    $response = get('/auth/sso/redirect')->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $verifier = session('code_verifier');

    expect(strtok($location, '?'))->toBe('https://sso.test/authorize')
        ->and($query)->toMatchArray([
            'client_id' => 'invoiceshelf',
            'redirect_uri' => url('/auth/oidc/callback'),
            'scope' => 'openid profile email',
            'response_type' => 'code',
            'state' => session('state'),
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ])
        ->and($query['state'])->toHaveLength(40)
        ->and($query)->not->toHaveKey('prompt');
});

test('an authorization endpoint with a query string of its own keeps it', function (array $settings, array $answers) {
    oidcIdentityProvider($answers, $settings);

    $response = get('/auth/sso/redirect')->assertRedirect();

    expect($response->headers->get('Location'))->toStartWith('https://sso.test/authorize?p=b2c_1_signin&client_id=invoiceshelf&')
        ->and(ssoQueryOf($response))->toMatchArray(['p' => 'b2c_1_signin', 'response_type' => 'code']);
})->with([
    'set' => [['authorize_url' => 'https://sso.test/authorize?p=b2c_1_signin'], []],
    'discovered' => [
        oidcIssuerSettings(),
        [oidcJson(oidcDiscovery(['authorization_endpoint' => 'https://sso.test/authorize?p=b2c_1_signin']))],
    ],
]);

test('the scopes come from OIDC_SCOPES, separated by spaces or commas', function () {
    configureOidc(['scopes' => 'openid email, groups']);

    $location = get('/auth/sso/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('openid email groups');
});

test('a fixed redirect URI replaces the one built from the request', function () {
    configureOidc(['redirect' => 'https://invoices.example.com/auth/oidc/callback']);

    $location = get('/auth/sso/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://invoices.example.com/auth/oidc/callback');
});

test('the callback trades the code for a token, reads the account and signs its user in', function () {
    $requests = oidcIdentityProvider([
        oidcJson([
            'access_token' => 'the-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'scope' => 'openid profile email',
        ]),
        oidcJson([
            'sub' => 'sso-account-1',
            'email' => seededAdmin()->email,
            'email_verified' => true,
            'name' => 'Jane Doe',
        ]),
    ]);

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    [$token, $userinfo] = array_column($requests->getArrayCopy(), 'request');
    parse_str((string) $token->getBody(), $fields);

    expect((string) $token->getUri())->toBe('https://sso.test/token')
        ->and($fields)->toMatchArray([
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'client_id' => 'invoiceshelf',
            'client_secret' => 'client-secret',
            'redirect_uri' => url('/auth/oidc/callback'),
            'code_verifier' => 'the-code-verifier',
        ])
        ->and($token->hasHeader('Authorization'))->toBeFalse()
        ->and((string) $userinfo->getUri())->toBe('https://sso.test/userinfo')
        ->and($userinfo->getHeaderLine('Authorization'))->toBe('Bearer the-access-token')
        ->and(oidcIdentityOf(seededAdmin())?->subject)->toBe('sso-account-1');

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('the identity provider gets 5 seconds to connect and 10 to answer, and 5 in all for discovery', function () {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery()),
        oidcTokenAnswer(),
        oidcUserinfoAnswer(),
    ], oidcIssuerSettings());

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    [$discovery, $token, $userinfo] = array_column($requests->getArrayCopy(), 'options');

    expect([$token['connect_timeout'], $token['timeout']])->toBe([5, 10])
        ->and([$userinfo['connect_timeout'], $userinfo['timeout']])->toBe([5, 10])
        ->and([$discovery['connect_timeout'], $discovery['timeout']])->toBe([5, 5]);
});

test('an identity provider that does not answer ends the sign-in with a logged reason', function (Closure $answers, string $reason) {
    Exceptions::fake();
    oidcIdentityProvider($answers());

    oidcCompleteSignIn()
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expectSsoLogged('warning', $reason);
    Exceptions::assertNothingReported();
})->with([
    'at the token endpoint' => [
        fn () => [new ConnectException('Operation timed out', new HttpRequest('POST', 'https://sso.test/token'))],
        'token endpoint did not answer',
    ],
    'at the userinfo endpoint' => [
        fn () => [oidcTokenAnswer(), new ConnectException('Connection refused', new HttpRequest('GET', 'https://sso.test/userinfo'))],
        'userinfo endpoint did not answer',
    ],
]);

test('the first sign-in links the user with the same email address, whatever its case', function () {
    $this->travelTo('2026-11-21 10:00:00');
    signInAs('sso-account-1', strtoupper(seededAdmin()->email));

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
    expect(oidcIdentityOf(seededAdmin()))
        ->subject->toBe('sso-account-1')
        ->issuer->toBe('https://sso.test')
        ->last_used_at->toDateTimeString()->toBe('2026-11-21 10:00:00');
});

test('a linked account signs in as its user even after an email address changed, and the link records when', function () {
    $identity = linkOidc(seededAdmin());
    $this->travelTo('2026-11-21 10:00:00');
    signInAs('sso-account-1', 'new-address@example.com');

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
    expect($identity->fresh()->last_used_at->toDateTimeString())->toBe('2026-11-21 10:00:00');
});

test('an account that matches no user is refused, and nobody is created', function () {
    $users = User::query()->count();
    signInAs('sso-account-1', 'stranger@example.com');

    oidcCallback()
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(User::query()->count())->toBe($users)
        ->and(SsoIdentity::query()->count())->toBe(0);
    expectSsoLogged('notice', 'no single user has this email address');
});

test('an account without an email address is refused', function () {
    signInAs('sso-account-1', null);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(oidcIdentityOf(seededAdmin()))->toBeNull();
});

test('an account without a subject fails the sign-in, and the log says why', function () {
    signInAs('', seededAdmin()->email);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expect(oidcIdentityOf(seededAdmin()))->toBeNull();
    expectSsoLogged('notice', '"sub"');
});

test('a userinfo answer without a "sub" claim fails the sign-in, and the log says why', function () {
    oidcIdentityProvider([oidcTokenAnswer(), oidcJson(['email' => seededAdmin()->email, 'email_verified' => true])]);

    oidcCompleteSignIn()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expect(oidcIdentityOf(seededAdmin()))->toBeNull();
    expectSsoLogged('notice', '"sub"');
});

test('a userinfo answer that is not a JSON object fails the sign-in, and the log says why', function (Response $answer) {
    oidcIdentityProvider([oidcTokenAnswer(), $answer]);

    oidcCompleteSignIn()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expectSsoLogged('notice', 'userinfo endpoint did not answer with a JSON object');
})->with([
    'a signed JWT' => [new Response(200, ['Content-Type' => 'application/jwt'], 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiJ4In0.c2ln')],
    'a list' => [new Response(200, ['Content-Type' => 'application/json'], '["sso-account-1"]')],
]);

test('a user linked to another account is not taken over by a new account with their email address', function () {
    linkOidc(seededAdmin());
    signInAs('sso-account-2', seededAdmin()->email);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->ssoIdentities()->pluck('subject')->all())->toBe(['sso-account-1']);
});

test('a cancelled or incomplete sign-in goes back to the login page with an error', function (string $query) {
    signInAs('sso-account-1', seededAdmin()->email);

    get('/auth/oidc/callback'.$query)
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
})->with([
    'refused' => ['?error=access_denied&state=the-state'],
    'no code' => ['?state=the-state'],
]);

test('a callback whose state does not match is refused before the identity provider is asked anything', function () {
    $requests = oidcIdentityProvider([]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    get('/auth/oidc/callback?code=the-code&state=another-state')
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expect($requests)->toHaveCount(0);
});

test('an error from the identity provider is reported and sends the user back to the login page', function () {
    Exceptions::fake();
    oidcIdentityProvider([oidcJson(['error' => 'invalid_grant'], 400)]);

    oidcCompleteSignIn()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    Exceptions::assertReported(ClientException::class);
});

test('the login page says why the sign-in failed', function () {
    $this->withSession([SsoLogin::ERROR => 'no_account']);

    expect(ssoOnLoginPage())->toMatchArray(['error' => 'no_account']);
});

test('signing in goes on to the safe next page the login page was given', function (string $next, string $lands) {
    signInAs('sso-account-1', seededAdmin()->email);

    get('/auth/sso/redirect?next='.urlencode($next))->assertRedirect();
    oidcCallback()->assertRedirect(url($lands));
})->with([
    'an SPA page' => ['/admin/invoices?page=2', '/admin/invoices?page=2'],
    'the OAuth consent screen' => ['/oauth/authorize?client_id=abc', '/oauth/authorize?client_id=abc'],
    'another site' => ['//evil.example/path', '/admin/dashboard'],
]);

test('a failed sign-in goes back to the login page with the page it was heading to', function () {
    signInAs('sso-account-1', 'stranger@example.com');

    get('/auth/sso/redirect?next='.urlencode('/oauth/authorize?client_id=abc'))->assertRedirect();

    oidcCallback()
        ->assertRedirect(route('login', ['next' => '/oauth/authorize?client_id=abc']))
        ->assertSessionHas(SsoLogin::ERROR, 'no_account');
});

test('a failed sign-in does not carry an unsafe next page to the login page', function () {
    signInAs('sso-account-1', 'stranger@example.com');

    get('/auth/sso/redirect?next='.urlencode('//evil.example/path'))->assertRedirect();

    oidcCallback()->assertRedirect(url('/login'));
});

test('a browser that is signed in already goes on without signing in again', function () {
    actingAs(seededAdmin(), 'web');

    get('/auth/sso/redirect')->assertRedirect();
    oidcCallback()->assertRedirect(url('/admin/dashboard'));
});

/*
 * Discovery: with OIDC_ISSUER set, the endpoints come from the issuer's
 * /.well-known/openid-configuration, unless an OIDC_*_URL names one.
 */

test('the endpoints come from the issuer\'s discovery document', function () {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery()),
        oidcTokenAnswer(),
        oidcUserinfoAnswer(),
    ], oidcIssuerSettings());

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    expect(array_map(fn (array $entry): string => (string) $entry['request']->getUri(), $requests->getArrayCopy()))->toBe([
        'https://sso.test/realms/acme/.well-known/openid-configuration',
        'https://sso.test/realms/acme/protocol/openid-connect/token',
        'https://sso.test/realms/acme/protocol/openid-connect/userinfo',
    ]);
    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('the redirect goes to the discovered authorization endpoint, and the document is kept for an hour', function () {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery()),
        oidcJson(oidcDiscovery()),
    ], oidcIssuerSettings());

    expect(strtok((string) get('/auth/sso/redirect')->headers->get('Location'), '?'))
        ->toBe('https://sso.test/realms/acme/protocol/openid-connect/auth');

    get('/auth/sso/redirect')->assertRedirect();
    expect($requests)->toHaveCount(1);

    $this->travel(61)->minutes();
    get('/auth/sso/redirect')->assertRedirect();
    expect($requests)->toHaveCount(2);
});

test('a trailing slash on the issuer works either way', function (string $configured, string $published) {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery(['issuer' => $published])),
    ], oidcIssuerSettings(['issuer' => $configured]));

    expect(strtok((string) get('/auth/sso/redirect')->headers->get('Location'), '?'))
        ->toBe('https://sso.test/realms/acme/protocol/openid-connect/auth')
        ->and((string) $requests[0]['request']->getUri())
        ->toBe('https://sso.test/application/o/invoiceshelf/.well-known/openid-configuration');
})->with([
    'with one, as Authentik has it' => ['https://sso.test/application/o/invoiceshelf/', 'https://sso.test/application/o/invoiceshelf/'],
    'without one, as Keycloak has it' => ['https://sso.test/application/o/invoiceshelf', 'https://sso.test/application/o/invoiceshelf'],
    'only in the setting' => ['https://sso.test/application/o/invoiceshelf/', 'https://sso.test/application/o/invoiceshelf'],
    'only in the document' => ['https://sso.test/application/o/invoiceshelf', 'https://sso.test/application/o/invoiceshelf/'],
]);

test('an endpoint URL that is set wins over the discovered one', function () {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery()),
        oidcTokenAnswer(),
        oidcUserinfoAnswer(),
    ], oidcIssuerSettings(['token_url' => 'https://internal.sso.test/token']));

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    expect(array_map(fn (array $entry): string => (string) $entry['request']->getUri(), $requests->getArrayCopy()))->toBe([
        'https://sso.test/realms/acme/.well-known/openid-configuration',
        'https://internal.sso.test/token',
        'https://sso.test/realms/acme/protocol/openid-connect/userinfo',
    ]);
});

test('with every URL and the client authentication set, the issuer is never asked for its document', function () {
    $requests = oidcIdentityProvider([], ['issuer' => 'https://sso.test/realms/acme', 'client_auth' => 'post']);

    expect(strtok((string) get('/auth/sso/redirect')->headers->get('Location'), '?'))->toBe('https://sso.test/authorize')
        ->and($requests)->toHaveCount(0);
});

test('a discovery document for another issuer is refused, and not kept', function () {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery(['issuer' => 'https://evil.test/realms/acme'])),
        oidcJson(oidcDiscovery(['issuer' => 'https://evil.test/realms/acme'])),
    ], oidcIssuerSettings());

    get('/auth/sso/redirect')
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');
    get('/auth/sso/redirect')->assertRedirect(url('/login'));

    expect($requests)->toHaveCount(2);
    expectSsoLogged('warning', 'does not match OIDC_ISSUER');
});

test('a discovery document that cannot be had is not kept, the login page never asks for it, and the sign-in fails cleanly', function (Closure $failure) {
    $requests = oidcIdentityProvider([$failure(), oidcJson(oidcDiscovery())], oidcIssuerSettings());

    // The login page and the client manifest only ask whether to offer the button.
    expect(ssoOnLoginPage())->not->toBeNull();
    getJson('/api/v1/app/client-manifest')->assertOk();
    expect($requests)->toHaveCount(0);

    get('/auth/sso/redirect')
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');
    expectSsoLogged('warning', 'discovery document');

    expect(strtok((string) get('/auth/sso/redirect')->headers->get('Location'), '?'))
        ->toBe('https://sso.test/realms/acme/protocol/openid-connect/auth')
        ->and($requests)->toHaveCount(2);
})->with([
    'a server error' => [fn () => new Response(500)],
    'not JSON' => [fn () => new Response(200, ['Content-Type' => 'text/html'], '<html></html>')],
    'no issuer in it' => [fn () => oidcJson(['authorization_endpoint' => 'https://sso.test/auth'])],
    'unreachable' => [fn () => new ConnectException('Connection timed out', new HttpRequest('GET', 'https://sso.test/realms/acme/.well-known/openid-configuration'))],
]);

test('a discovery document without an endpoint that is not set either fails the sign-in', function () {
    oidcIdentityProvider([oidcJson(oidcDiscovery(['userinfo_endpoint' => null])), oidcTokenAnswer()], oidcIssuerSettings());

    oidcCompleteSignIn()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expectSsoLogged('warning', 'OIDC_USERINFO_URL');
});

/*
 * How the client proves itself at the token endpoint: OIDC_CLIENT_AUTH.
 */

test('OIDC_CLIENT_AUTH=basic sends the client secret in an Authorization header and not in the body', function () {
    $requests = oidcIdentityProvider([oidcTokenAnswer(), oidcUserinfoAnswer()], ['client_auth' => 'basic']);

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    $token = $requests[0]['request'];
    parse_str((string) $token->getBody(), $fields);

    expect($token->getHeaderLine('Authorization'))->toBe('Basic '.base64_encode('invoiceshelf:client-secret'))
        ->and($fields)->not->toHaveKey('client_secret')
        ->and($fields)->toMatchArray(['client_id' => 'invoiceshelf', 'code' => 'the-code', 'code_verifier' => 'the-code-verifier']);
});

test('OIDC_CLIENT_AUTH=auto picks the method the discovery document allows', function (?array $methods, bool $basic) {
    $requests = oidcIdentityProvider([
        oidcJson(oidcDiscovery(['token_endpoint_auth_methods_supported' => $methods])),
        oidcTokenAnswer(),
        oidcUserinfoAnswer(),
    ], oidcIssuerSettings());

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    $token = $requests[1]['request'];
    parse_str((string) $token->getBody(), $fields);

    expect($token->hasHeader('Authorization'))->toBe($basic)
        ->and(array_key_exists('client_secret', $fields))->toBe(! $basic);
})->with([
    'basic only' => [['client_secret_basic', 'private_key_jwt'], true],
    'post and basic' => [['client_secret_basic', 'client_secret_post'], false],
    'not listed' => [null, false],
    'neither' => [['private_key_jwt'], false],
]);

/*
 * ID tokens: checked when the token endpoint sends one.
 */

test('an ID token that passes the checks signs in', function (array $claims, array $settings) {
    oidcIdentityProvider([oidcTokenAnswer(oidcIdToken($claims)), oidcUserinfoAnswer()], ['client_auth' => 'post', ...$settings]);

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
})->with([
    'plain' => [[], []],
    'with several audiences' => [['aud' => ['another-client', 'invoiceshelf']], []],
    // Worked out when the test runs: a dataset is built when the file loads, long before.
    'expired within the allowed skew' => [fn (): array => ['exp' => time() - 30], []],
    'from the configured issuer' => [[], ['issuer' => 'https://sso.test/realms/acme']],
    'from the configured issuer, but for a trailing slash' => [['iss' => 'https://sso.test/realms/acme/'], ['issuer' => 'https://sso.test/realms/acme']],
]);

test('an ID token that fails a check refuses the sign-in', function (array $claims) {
    oidcIdentityProvider([oidcTokenAnswer(oidcIdToken($claims)), oidcUserinfoAnswer()], [
        'issuer' => 'https://sso.test/realms/acme',
        'client_auth' => 'post',
    ]);

    oidcCompleteSignIn()
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expect(oidcIdentityOf(seededAdmin()))->toBeNull();
    expectSsoLogged('notice', 'ID token');
})->with([
    'for another client' => [['aud' => 'another-client']],
    'for other clients only' => [['aud' => ['another-client', 'a-third-client']]],
    'for no client' => [['aud' => null]],
    'expired' => [['exp' => time() - 3600]],
    'without an expiry' => [['exp' => null]],
    'from another issuer' => [['iss' => 'https://evil.test/realms/acme']],
    'about another account' => [['sub' => 'sso-account-2']],
]);

test('an ID token that cannot be decoded refuses the sign-in', function (string $idToken) {
    oidcIdentityProvider([oidcTokenAnswer($idToken), oidcUserinfoAnswer()]);

    oidcCompleteSignIn()->assertSessionHas(SsoLogin::ERROR, 'failed');

    $this->assertGuest('web');
    expectSsoLogged('notice', 'ID token');
})->with([
    'not a JWT' => ['not-a-jwt'],
    'not JSON inside' => ['eyJhbGciOiJSUzI1NiJ9.'.rtrim(base64_encode('not json'), '=').'.c2ln'],
    'encrypted' => ['eyJhbGciOiJSU0EtT0FFUCJ9.a.b.c.d'],
]);

/*
 * The link: an sso_identities row of type "oidc" naming the issuer and the
 * subject ("sub").
 */

test('the first sign-in stores the issuer with the link', function (Closure $answers, array $settings, string $issuer) {
    oidcIdentityProvider($answers(), $settings);

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    expect(oidcIdentityOf(seededAdmin()))
        ->subject->toBe('sso-account-1')
        ->issuer->toBe($issuer);
})->with([
    'the ID token\'s, without its trailing slash' => [
        fn () => [oidcTokenAnswer(oidcIdToken(['iss' => 'https://sso.test/application/o/invoiceshelf/'])), oidcUserinfoAnswer()],
        [],
        'https://sso.test/application/o/invoiceshelf',
    ],
    'the configured one' => [
        fn () => [oidcJson(oidcDiscovery()), oidcTokenAnswer(), oidcUserinfoAnswer()],
        oidcIssuerSettings(),
        'https://sso.test/realms/acme',
    ],
    'the token endpoint\'s origin' => [
        fn () => [oidcTokenAnswer(), oidcUserinfoAnswer()],
        [],
        'https://sso.test',
    ],
]);

test('the same subject from another issuer is not the link, and cannot take the user over by email address', function () {
    linkOidc(seededAdmin(), 'sso-account-1', 'https://old-sso.test');
    signInAs('sso-account-1', seededAdmin()->email);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->ssoIdentities()->pluck('issuer')->all())->toBe(['https://old-sso.test']);
    expectSsoLogged('notice', 'linked to another account');
});

test('a subject the database matches only by ignoring case is not the link', function () {
    // MySQL's default collation compares like this; SQLite has to be told to.
    Schema::table('sso_identities', fn (Blueprint $table) => $table->string('subject')->collation('nocase')->change());
    linkOidc(seededAdmin(), 'alice');
    expect(SsoIdentity::query()->where('subject', 'ALICE')->exists())->toBeTrue();

    signInAs('ALICE', 'stranger@example.com');

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'no_account');
    $this->assertGuest('web');
});

test('an identity of another sign-on type does not stand in the way of the first OpenID Connect link', function () {
    SsoIdentity::factory()->for(seededAdmin())->create(['type' => 'saml', 'issuer' => 'https://idp.example', 'subject' => 'sso-account-1']);
    signInAs('sso-account-1', seededAdmin()->email);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    expect(seededAdmin()->ssoIdentities()->orderBy('type')->pluck('type')->all())->toBe(['oidc', 'saml']);
});

test('an account is linked to one user only, and a user to one account per sign-on type', function (array $duplicate) {
    $identity = linkOidc(seededAdmin());

    expect(fn () => SsoIdentity::factory()->create([
        'user_id' => $duplicate['same_user'] ? seededAdmin()->id : User::factory()->create()->id,
        'type' => $identity->type,
        'issuer' => $duplicate['issuer'] ?? $identity->issuer,
        'subject' => $duplicate['subject'] ?? $identity->subject,
    ]))->toThrow(UniqueConstraintViolationException::class);
})->with([
    'the same account for another user' => [['same_user' => false]],
    'a second account for the same user' => [['same_user' => true, 'subject' => 'sso-account-2']],
]);

test('two first sign-ins of one account at once link it once, and both sign in', function () {
    oidcRaceToLink(['user_id' => seededAdmin()->id, 'issuer' => 'https://sso.test', 'subject' => 'sso-account-1']);
    signInAs('sso-account-1', seededAdmin()->email);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
    expect(SsoIdentity::query()->count())->toBe(1);
});

test('two first sign-ins of different accounts for one user at once link only one', function () {
    oidcRaceToLink(['user_id' => seededAdmin()->id, 'issuer' => 'https://sso.test', 'subject' => 'sso-account-2']);
    signInAs('sso-account-1', seededAdmin()->email);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->ssoIdentities()->pluck('subject')->all())->toBe(['sso-account-2']);
    expectSsoLogged('notice', 'linked to another account');
});

test('deleting a user deletes their identities', function () {
    $member = User::factory()->create();
    $member->companies()->attach(seededAdmin()->companies()->first()->id);
    linkOidc($member);
    $other = User::factory()->create();
    linkOidc($other, 'sso-account-2');

    Sanctum::actingAs(seededAdmin(), ['*']);
    $this->withHeaders(['company' => seededAdmin()->companies()->first()->id]);
    postJson('/api/v1/members/delete', ['users' => [$member->id]])->assertOk();

    expect(SsoIdentity::query()->pluck('user_id')->all())->toBe([$other->id]);
});

/*
 * The first link needs an email address the identity provider has verified.
 */

test('the first sign-in is refused for an email address the identity provider has not verified', function (mixed $verified) {
    signInAs('sso-account-1', seededAdmin()->email, ['email_verified' => $verified]);

    oidcCallback()
        ->assertRedirect(url('/login'))
        ->assertSessionHas(SsoLogin::ERROR, 'email_unverified');

    $this->assertGuest('web');
    expect(oidcIdentityOf(seededAdmin()))->toBeNull();
    expectSsoLogged('notice', 'email_verified');
})->with([
    'false' => [false],
    'the string "false"' => ['false'],
    'not sent' => [null],
    'a number' => [1],
]);

test('the refusal for an unverified address does not tell whether a user has it', function () {
    signInAs('sso-account-1', 'stranger@example.com', ['email_verified' => false]);

    oidcCallback()->assertSessionHas(SsoLogin::ERROR, 'email_unverified');
});

test('a verified email address may be true or the string "true"', function (mixed $verified) {
    signInAs('sso-account-1', seededAdmin()->email, ['email_verified' => $verified]);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    expect(oidcIdentityOf(seededAdmin()))->not->toBeNull();
})->with([
    'true' => [true],
    'the string "true"' => ['true'],
]);

test('the ID token may say the address is verified when the userinfo answer does not', function () {
    oidcIdentityProvider([
        oidcTokenAnswer(oidcIdToken(['email_verified' => true])),
        oidcUserinfoAnswer(['email_verified' => null]),
    ]);

    oidcCompleteSignIn()->assertRedirect(url('/admin/dashboard'));

    expect(oidcIdentityOf(seededAdmin()))->not->toBeNull();
});

test('OIDC_REQUIRE_VERIFIED_EMAIL=false links an unverified address', function () {
    configureOidc(['require_verified_email' => false]);
    signInAs('sso-account-1', seededAdmin()->email, ['email_verified' => false]);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    expect(oidcIdentityOf(seededAdmin()))->not->toBeNull();
});

test('a linked user signs in whatever email_verified says', function () {
    linkOidc(seededAdmin());
    signInAs('sso-account-1', seededAdmin()->email, ['email_verified' => false]);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

/*
 * sso:unlink
 */

test('sso:unlink removes every identity of the user, so the next sign-in links by email address again', function () {
    linkOidc(seededAdmin());
    SsoIdentity::factory()->for(seededAdmin())->create(['type' => 'saml']);

    artisan('sso:unlink', ['email' => strtoupper(seededAdmin()->email)])
        ->expectsOutputToContain('Unlinked')
        ->assertSuccessful();

    expect(seededAdmin()->ssoIdentities()->count())->toBe(0);

    signInAs('sso-account-2', seededAdmin()->email);
    oidcCallback()->assertRedirect(url('/admin/dashboard'));

    expect(oidcIdentityOf(seededAdmin())->subject)->toBe('sso-account-2');
});

test('sso:unlink --type removes only the identities of that sign-on type', function () {
    linkOidc(seededAdmin());
    SsoIdentity::factory()->for(seededAdmin())->create(['type' => 'saml']);

    artisan('sso:unlink', ['email' => seededAdmin()->email, '--type' => 'oidc'])->assertSuccessful();

    expect(seededAdmin()->ssoIdentities()->pluck('type')->all())->toBe(['saml']);

    artisan('sso:unlink', ['email' => seededAdmin()->email, '--type' => 'oidc'])
        ->expectsOutputToContain('not linked')
        ->assertSuccessful();
});

test('sso:unlink fails for an unknown email address', function () {
    artisan('sso:unlink', ['email' => 'nobody@example.com'])->assertFailed();
});

/*
 * Rate limits.
 */

test('each sign-in route counts against a limit of its own', function () {
    $throttles = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'auth/sso/') || str_contains($route->uri(), 'auth/oidc/'))
        ->map(fn ($route): array => array_values(array_filter($route->gatherMiddleware(), fn ($middleware): bool => is_string($middleware) && str_starts_with($middleware, 'throttle:'))));

    expect($throttles)->toHaveCount(7)
        ->and($throttles->every(fn (array $throttle): bool => count($throttle) === 1))->toBeTrue()
        ->and($throttles->map(fn (array $throttle): string => explode(',', $throttle[0])[2] ?? '')->unique())->toHaveCount(7);
});

test('starting many web sign-ins does not throw away one that is finishing', function () {
    signInAs('sso-account-1', seededAdmin()->email);

    $attempts = 0;
    do {
        $status = get('/auth/sso/redirect')->getStatusCode();
    } while ($status !== 429 && ++$attempts < 100);

    expect($status)->toBe(429);
    oidcCallback()->assertRedirect(url('/admin/dashboard'));
});

/*
 * The mobile app (SsoHandoffs): it starts a hand-off on the API, signs in in
 * the system browser, and trades the code that browser brings back to the
 * app's link, with its secret, for an access token.
 */

/** The app starts a hand-off for its device. */
function startHandoff(string $device = 'Pixel 9'): array
{
    return postJson('/api/v1/auth/sso/handoffs', ['device_name' => $device])->assertCreated()->json();
}

/** The query of the app link a callback sent the browser to. */
function appLinkFrom(TestResponse $response): array
{
    $location = (string) $response->assertRedirect()->headers->get('Location');

    expect(strtok($location, '?'))->toBe('com.invoiceshelf.app:/sso');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

/** The app trades the code and its secret for a token. */
function redeemHandoff(array $handoff, ?string $code, ?string $secret = null): TestResponse
{
    return postJson("/api/v1/auth/sso/handoffs/{$handoff['id']}/token", [
        'secret' => $secret ?? $handoff['secret'],
        'code' => $code,
    ]);
}

test('the mobile app starts a hand-off and gets the address to open and a secret', function () {
    $handoff = startHandoff();

    expect($handoff['url'])->toBe(route('sso.app', ['handoff' => $handoff['id']]))
        ->and($handoff['url'])->toContain('/auth/sso/app/')
        ->and($handoff['app_link'])->toBe('com.invoiceshelf.app:/sso')
        ->and($handoff['secret'])->toHaveLength(64)
        ->and($handoff['id'])->toHaveLength(40);
});

test('the hand-off API wants a device name and is off until single sign-on is set up', function () {
    postJson('/api/v1/auth/sso/handoffs', [])->assertUnprocessable()->assertJsonValidationErrors('device_name');

    configureOidc(['userinfo_url' => null]);

    postJson('/api/v1/auth/sso/handoffs', ['device_name' => 'Pixel 9'])->assertNotFound();
});

test('the browser the app opens asks its user to confirm before anything goes to the identity provider', function () {
    $handoff = startHandoff('Pixel 9');

    get($handoff['url'])
        ->assertOk()
        ->assertSee('Sign in to the InvoiceShelf app on Pixel 9?')
        ->assertSee(route('sso.app.continue', ['handoff' => $handoff['id']]), false)
        ->assertHeader('X-Frame-Options', 'DENY');

    expect(session(SsoLogin::HANDOFF))->toBeNull();
});

test('the confirmation escapes the device name, which the app chose, and keeps it short', function () {
    $handoff = startHandoff('<b>Evil</b> '.str_repeat('x', 200));

    get($handoff['url'])
        ->assertOk()
        ->assertSee('&lt;b&gt;Evil&lt;/b&gt;', false)
        ->assertDontSee('<b>Evil</b>', false)
        ->assertDontSee(str_repeat('x', 80));
});

test('continuing starts the sign-in at the identity provider, asking it to sign the person in again', function () {
    $handoff = startHandoff();

    $response = post($handoff['url'])->assertRedirect();

    expect(strtok((string) $response->headers->get('Location'), '?'))->toBe('https://sso.test/authorize')
        ->and(ssoQueryOf($response))->toMatchArray(['prompt' => 'login'])
        ->and(session(SsoLogin::HANDOFF))->toBe($handoff['id']);
});

test('only a POST with the session\'s CSRF token goes on from the confirmation', function () {
    $handoff = startHandoff();

    expect(Route::getRoutes()->getByName('sso.app.continue')->methods())->toBe(['POST'])
        ->and(Route::getRoutes()->getByName('sso.app.cancel')->methods())->toBe(['DELETE']);

    // The CSRF check stands aside in tests; outside them it stands.
    app()['env'] = 'local';

    try {
        post($handoff['url'])->assertStatus(419);
        delete($handoff['url'])->assertStatus(419);
    } finally {
        app()['env'] = 'testing';
    }

    expect(app(SsoHandoffs::class)->pending($handoff['id']))->toBeTrue();
});

test('cancelling ends the hand-off and tells the app', function () {
    $handoff = startHandoff();

    expect(appLinkFrom(delete($handoff['url'])))->toBe(['handoff' => $handoff['id'], 'error' => 'cancelled']);

    get($handoff['url'])->assertNotFound();
    post($handoff['url'])->assertNotFound();
});

test('an unknown hand-off has no sign-in to start', function () {
    get(route('sso.app', ['handoff' => str_repeat('x', 40)]))->assertNotFound();
    post(route('sso.app', ['handoff' => str_repeat('x', 40)]))->assertNotFound();
});

test('a hand-off whose discovery fails goes back to the app with an error', function () {
    oidcIdentityProvider([new Response(500)], oidcIssuerSettings());
    $handoff = startHandoff();

    expect(appLinkFrom(post($handoff['url'])))->toBe(['handoff' => $handoff['id'], 'error' => 'failed']);
    expectSsoLogged('warning', 'discovery document');
});

test('the sign-in sends the browser back to the app with a code that, with the secret, buys a token', function () {
    $handoff = startHandoff('Pixel 9');
    post($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);

    $link = appLinkFrom(oidcCallback());

    expect($link['handoff'])->toBe($handoff['id'])
        ->and($link['code'])->toHaveLength(64);
    $this->assertGuest('web');

    $token = redeemHandoff($handoff, $link['code'])->assertOk()->assertJson(['type' => 'Bearer'])->json('token');

    expect(PersonalAccessToken::findToken($token)->tokenable->is(seededAdmin()))->toBeTrue()
        ->and(seededAdmin()->tokens()->pluck('name')->all())->toBe(['Pixel 9'])
        ->and(oidcIdentityOf(seededAdmin())->subject)->toBe('sso-account-1');
});

test('the code buys nothing without the secret, and a wrong try ends the hand-off', function () {
    $handoff = startHandoff();
    post($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oidcCallback());

    redeemHandoff($handoff, $link['code'], secret: 'not-the-secret')->assertUnprocessable();
    redeemHandoff($handoff, $link['code'])->assertUnprocessable();

    expect(seededAdmin()->tokens()->count())->toBe(0);
});

test('a code buys one token only', function () {
    $handoff = startHandoff();
    post($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oidcCallback());

    redeemHandoff($handoff, $link['code'])->assertOk();
    redeemHandoff($handoff, $link['code'])->assertUnprocessable();

    expect(seededAdmin()->tokens()->count())->toBe(1);
});

test('a hand-off whose sign-in has not finished buys nothing', function () {
    $handoff = startHandoff();

    redeemHandoff($handoff, str_repeat('x', 64))->assertUnprocessable();
});

test('two callbacks for one hand-off cannot both complete it', function () {
    $handoffs = app(SsoHandoffs::class);
    ['id' => $id] = $handoffs->start('Pixel 9');
    $pending = Cache::get("sso_handoff:{$id}");

    expect($handoffs->complete($id, seededAdmin()))->toBeString();

    // The second callback read the hand-off before the first one wrote it.
    Cache::put("sso_handoff:{$id}", $pending, SsoHandoffs::SIGN_IN_SECONDS);

    expect($handoffs->complete($id, User::factory()->create()))->toBeNull();
});

test('a refused or failed sign-in sends the browser back to the app with the reason', function (string $email, string $query, string $error) {
    $handoff = startHandoff();
    post($handoff['url']);
    signInAs('sso-account-1', $email);

    $link = get('/auth/oidc/callback'.$query)->assertRedirect();

    expect(appLinkFrom($link))->toBe(['handoff' => $handoff['id'], 'error' => $error]);
    redeemHandoff($handoff, 'anything')->assertUnprocessable();
})->with([
    'no matching user' => ['stranger@example.com', '?code=the-code&state=the-state', 'no_account'],
    'cancelled at the identity provider' => ['admin@invoiceshelf.com', '?error=access_denied&state=the-state', 'failed'],
]);

test('a browser signed in to the web app still finishes the app sign-in, for the SSO account', function () {
    actingAs(User::factory()->create(), 'web');
    $handoff = startHandoff();

    post($handoff['url'])->assertRedirect();
    signInAs('sso-account-1', seededAdmin()->email);
    $token = redeemHandoff($handoff, appLinkFrom(oidcCallback())['code'])->assertOk()->json('token');

    expect(PersonalAccessToken::findToken($token)->tokenable->is(seededAdmin()))->toBeTrue();
});

test('starting the web sign-in drops a hand-off the browser abandoned', function () {
    $handoff = startHandoff();
    post($handoff['url']);

    get('/auth/sso/redirect')->assertRedirect();
    signInAs('sso-account-1', seededAdmin()->email);

    oidcCallback()->assertRedirect(url('/admin/dashboard'));
    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('a hand-off left too long expires, and so does an unused code', function () {
    $handoff = startHandoff();
    $this->travel(SsoHandoffs::SIGN_IN_SECONDS + 1)->seconds();

    get($handoff['url'])->assertNotFound();

    $handoff = startHandoff();
    post($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oidcCallback());
    $this->travel(SsoHandoffs::CODE_SECONDS + 1)->seconds();

    redeemHandoff($handoff, $link['code'])->assertUnprocessable();
});

test('the client manifest tells the mobile app whether to offer single sign-on', function () {
    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('sso_login'))->toBeTrue();

    configureOidc(['token_url' => null]);

    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('sso_login'))->toBeFalse();
});

test('starting many hand-offs does not throw away one whose code is being traded', function () {
    $handoff = startHandoff();
    post($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oidcCallback());

    $attempts = 0;
    do {
        $status = postJson('/api/v1/auth/sso/handoffs', ['device_name' => 'Pixel 9'])->getStatusCode();
    } while ($status !== 429 && ++$attempts < 100);

    expect($status)->toBe(429);
    redeemHandoff($handoff, $link['code'])->assertOk();
});
