<?php

use App\Domains\Accounts\Application\OAuth2Handoffs;
use App\Domains\Accounts\Application\OAuth2Login;
use App\Domains\Accounts\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as OAuth2Account;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/** Turns sign-in with SSO on, pointed at a made-up identity provider. */
function configureOAuth2(array $settings = []): void
{
    config(['services.oauth2' => [
        'client_id' => 'invoiceshelf',
        'client_secret' => 'client-secret',
        'authorize_url' => 'https://sso.test/authorize',
        'token_url' => 'https://sso.test/token',
        'userinfo_url' => 'https://sso.test/userinfo',
        'scopes' => 'openid profile email',
        'redirect' => '/auth/oauth2/callback',
        ...$settings,
    ]]);
}

/** The identity provider answers the callback with this account. */
function signInAs(string $id, ?string $email): void
{
    Socialite::fake('oauth2', (new OAuth2Account)->map(['id' => $id, 'email' => $email]));
}

/** The callback the identity provider sends the browser back to. */
function oauth2Callback(): TestResponse
{
    return get('/auth/oauth2/callback?code=the-code&state=the-state');
}

/** What the Blade shell passes to the SPA about sign-in with SSO. */
function oauth2OnLoginPage(): ?array
{
    test()->withoutVite();

    if (! preg_match('/window\.oauth2_login = (\{.*?\});/', view('app')->render(), $match)) {
        return null;
    }

    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}

function seededAdmin(): User
{
    return User::query()->where('role', 'super admin')->orderBy('id')->firstOrFail();
}

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    configureOAuth2();
});

test('sign-in with SSO is off until the client and the three endpoint URLs are set', function (string $setting) {
    configureOAuth2([$setting => null]);

    get('/auth/oauth2/redirect')->assertNotFound();
    oauth2Callback()->assertNotFound();

    expect(oauth2OnLoginPage())->toBeNull();
})->with(['client_id', 'client_secret', 'authorize_url', 'token_url', 'userinfo_url']);

test('the login page offers sign-in with SSO', function () {
    expect(oauth2OnLoginPage())->toBe([
        'url' => '/auth/oauth2/redirect',
        'error' => null,
    ]);
});

test('the redirect asks the identity provider for an authorization code with PKCE and the configured scopes', function () {
    $response = get('/auth/oauth2/redirect')->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $verifier = session('code_verifier');

    expect(strtok($location, '?'))->toBe('https://sso.test/authorize')
        ->and($query)->toMatchArray([
            'client_id' => 'invoiceshelf',
            'redirect_uri' => url('/auth/oauth2/callback'),
            'scope' => 'openid profile email',
            'response_type' => 'code',
            'state' => session('state'),
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ])
        ->and($query['state'])->toHaveLength(40);
});

test('the scopes come from OAUTH2_SCOPES, separated by spaces or commas', function () {
    configureOAuth2(['scopes' => 'openid email, groups']);

    $location = get('/auth/oauth2/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('openid email groups');
});

test('a fixed redirect URI replaces the one built from the request', function () {
    configureOAuth2(['redirect' => 'https://invoices.example.com/auth/oauth2/callback']);

    $location = get('/auth/oauth2/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://invoices.example.com/auth/oauth2/callback');
});

test('the callback trades the code for a token, reads the account and signs its user in', function () {
    $requests = [];
    $identityProvider = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'access_token' => 'the-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'scope' => 'openid profile email',
        ])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'sub' => 'sso-account-1',
            'email' => seededAdmin()->email,
            'email_verified' => false,
            'name' => 'Jane Doe',
        ])),
    ]));
    $identityProvider->push(Middleware::history($requests));
    configureOAuth2(['guzzle' => ['handler' => $identityProvider]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    oauth2Callback()->assertRedirect(url('/admin/dashboard'));

    [$token, $userinfo] = array_column($requests, 'request');
    parse_str((string) $token->getBody(), $fields);

    expect((string) $token->getUri())->toBe('https://sso.test/token')
        ->and($fields)->toMatchArray([
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'client_id' => 'invoiceshelf',
            'client_secret' => 'client-secret',
            'redirect_uri' => url('/auth/oauth2/callback'),
            'code_verifier' => 'the-code-verifier',
        ])
        ->and((string) $userinfo->getUri())->toBe('https://sso.test/userinfo')
        ->and($userinfo->getHeaderLine('Authorization'))->toBe('Bearer the-access-token')
        ->and(seededAdmin()->oauth2_id)->toBe('sso-account-1');

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('the first sign-in links the user with the same email address, whatever its case', function () {
    signInAs('sso-account-1', strtoupper(seededAdmin()->email));

    oauth2Callback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
    expect(seededAdmin()->oauth2_id)->toBe('sso-account-1');
});

test('a linked account signs in as its user even after an email address changed', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();
    signInAs('sso-account-1', 'new-address@example.com');

    oauth2Callback()->assertRedirect(url('/admin/dashboard'));

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('an account that matches no user is refused, and nobody is created', function () {
    $users = User::query()->count();
    signInAs('sso-account-1', 'stranger@example.com');

    oauth2Callback()
        ->assertRedirect(url('/login'))
        ->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(User::query()->count())->toBe($users);
});

test('an account without an email address or a subject is refused', function (string $id, ?string $email) {
    signInAs($id, $email);

    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBeNull();
})->with([
    'no email address' => ['sso-account-1', null],
    'no subject' => ['', 'admin@invoiceshelf.com'],
]);

test('a userinfo answer without a "sub" claim is refused', function () {
    configureOAuth2(['guzzle' => ['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'the-access-token', 'token_type' => 'Bearer'])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['email' => seededAdmin()->email])),
    ]))]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBeNull();
});

test('a user linked to another account is not taken over by a new account with their email address', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();
    signInAs('sso-account-2', seededAdmin()->email);

    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBe('sso-account-1');
});

test('a cancelled or incomplete sign-in goes back to the login page with an error', function (string $query) {
    signInAs('sso-account-1', seededAdmin()->email);

    get('/auth/oauth2/callback'.$query)
        ->assertRedirect(url('/login'))
        ->assertSessionHas(OAuth2Login::ERROR, 'failed');

    $this->assertGuest('web');
})->with([
    'refused' => ['?error=access_denied&state=the-state'],
    'no code' => ['?state=the-state'],
]);

test('a callback whose state does not match is refused before the identity provider is asked anything', function () {
    $requests = [];
    $identityProvider = HandlerStack::create(new MockHandler([]));
    $identityProvider->push(Middleware::history($requests));
    configureOAuth2(['guzzle' => ['handler' => $identityProvider]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    get('/auth/oauth2/callback?code=the-code&state=another-state')
        ->assertRedirect(url('/login'))
        ->assertSessionHas(OAuth2Login::ERROR, 'failed');

    $this->assertGuest('web');
    expect($requests)->toBeEmpty();
});

test('an error from the identity provider is reported and sends the user back to the login page', function () {
    Exceptions::fake();
    configureOAuth2(['guzzle' => ['handler' => HandlerStack::create(new MockHandler([
        new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant'])),
    ]))]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'failed');

    $this->assertGuest('web');
    Exceptions::assertReported(ClientException::class);
});

test('the login page says why the sign-in failed', function () {
    $this->withSession([OAuth2Login::ERROR => 'no_account']);

    expect(oauth2OnLoginPage())->toMatchArray(['error' => 'no_account']);
});

test('signing in goes on to the safe next page the login page was given', function (string $next, string $lands) {
    signInAs('sso-account-1', seededAdmin()->email);

    get('/auth/oauth2/redirect?next='.urlencode($next))->assertRedirect();
    oauth2Callback()->assertRedirect(url($lands));
})->with([
    'an SPA page' => ['/admin/invoices?page=2', '/admin/invoices?page=2'],
    'the OAuth consent screen' => ['/oauth/authorize?client_id=abc', '/oauth/authorize?client_id=abc'],
    'another site' => ['//evil.example/path', '/admin/dashboard'],
]);

test('a browser that is signed in already goes on without signing in again', function () {
    actingAs(seededAdmin(), 'web');

    get('/auth/oauth2/redirect')->assertRedirect();
    oauth2Callback()->assertRedirect(url('/admin/dashboard'));
});

test('oauth2:unlink removes the link, so the next sign-in links by email address again', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();

    artisan('oauth2:unlink', ['email' => strtoupper(seededAdmin()->email)])
        ->expectsOutputToContain('Unlinked')
        ->assertSuccessful();

    expect(seededAdmin()->oauth2_id)->toBeNull();

    signInAs('sso-account-2', seededAdmin()->email);
    oauth2Callback()->assertRedirect(url('/admin/dashboard'));

    expect(seededAdmin()->oauth2_id)->toBe('sso-account-2');
});

test('oauth2:unlink fails for an unknown email address', function () {
    artisan('oauth2:unlink', ['email' => 'nobody@example.com'])->assertFailed();
});

test('the profile, member and user APIs cannot link a user to an SSO account', function () {
    $admin = seededAdmin();
    $companyId = $admin->companies()->first()->id;
    $colleague = User::factory()->create();
    $colleague->companies()->attach($companyId);

    Sanctum::actingAs($admin, ['*']);
    $this->withHeaders(['company' => $companyId]);

    putJson('/api/v1/me', [
        'name' => $admin->name,
        'email' => $admin->email,
        'oauth2_id' => 'chosen-by-the-caller',
    ])->assertOk();

    putJson("/api/v1/members/{$colleague->id}", [
        'name' => $colleague->name,
        'email' => $colleague->email,
        'companies' => [['id' => $companyId, 'role' => 'owner']],
        'oauth2_id' => 'chosen-by-the-caller',
    ])->assertOk();

    putJson("api/v1/super-admin/users/{$colleague->id}", [
        'name' => $colleague->name,
        'email' => $colleague->email,
        'oauth2_id' => 'chosen-by-the-caller',
    ])->assertOk();

    expect(User::query()->whereNotNull('oauth2_id')->exists())->toBeFalse();
});

/*
 * The mobile app (OAuth2Handoffs): it starts a hand-off on the API, signs in
 * in the system browser, and trades the code that browser brings back to the
 * app's link, with its secret, for an access token.
 */

/** The app starts a hand-off for its device. */
function startHandoff(string $device = 'Pixel 9'): array
{
    return postJson('/api/v1/auth/oauth2/handoffs', ['device_name' => $device])->assertCreated()->json();
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
    return postJson("/api/v1/auth/oauth2/handoffs/{$handoff['id']}/token", [
        'secret' => $secret ?? $handoff['secret'],
        'code' => $code,
    ]);
}

test('the mobile app starts a hand-off and gets the address to open and a secret', function () {
    $handoff = startHandoff();

    expect($handoff['url'])->toBe(route('oauth2.app', ['handoff' => $handoff['id']]))
        ->and($handoff['app_link'])->toBe('com.invoiceshelf.app:/sso')
        ->and($handoff['secret'])->toHaveLength(64)
        ->and($handoff['id'])->toHaveLength(40);
});

test('the hand-off API wants a device name and is off until sign-in with SSO is set up', function () {
    postJson('/api/v1/auth/oauth2/handoffs', [])->assertUnprocessable()->assertJsonValidationErrors('device_name');

    configureOAuth2(['userinfo_url' => null]);

    postJson('/api/v1/auth/oauth2/handoffs', ['device_name' => 'Pixel 9'])->assertNotFound();
});

test('the browser the app opens starts the sign-in at the identity provider', function () {
    $handoff = startHandoff();

    $location = get($handoff['url'])->assertRedirect()->headers->get('Location');

    expect(strtok($location, '?'))->toBe('https://sso.test/authorize')
        ->and(session(OAuth2Login::HANDOFF))->toBe($handoff['id']);
});

test('an unknown hand-off has no sign-in to start', function () {
    get(route('oauth2.app', ['handoff' => str_repeat('x', 40)]))->assertNotFound();
});

test('the sign-in sends the browser back to the app with a code that, with the secret, buys a token', function () {
    $handoff = startHandoff('Pixel 9');
    get($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);

    $link = appLinkFrom(oauth2Callback());

    expect($link['handoff'])->toBe($handoff['id'])
        ->and($link['code'])->toHaveLength(64);
    $this->assertGuest('web');

    $token = redeemHandoff($handoff, $link['code'])->assertOk()->assertJson(['type' => 'Bearer'])->json('token');

    expect(PersonalAccessToken::findToken($token)->tokenable->is(seededAdmin()))->toBeTrue()
        ->and(seededAdmin()->tokens()->pluck('name')->all())->toBe(['Pixel 9'])
        ->and(seededAdmin()->oauth2_id)->toBe('sso-account-1');
});

test('the code buys nothing without the secret, and a wrong try ends the hand-off', function () {
    $handoff = startHandoff();
    get($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oauth2Callback());

    redeemHandoff($handoff, $link['code'], secret: 'not-the-secret')->assertUnprocessable();
    redeemHandoff($handoff, $link['code'])->assertUnprocessable();

    expect(seededAdmin()->tokens()->count())->toBe(0);
});

test('a code buys one token only', function () {
    $handoff = startHandoff();
    get($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oauth2Callback());

    redeemHandoff($handoff, $link['code'])->assertOk();
    redeemHandoff($handoff, $link['code'])->assertUnprocessable();

    expect(seededAdmin()->tokens()->count())->toBe(1);
});

test('a hand-off whose sign-in has not finished buys nothing', function () {
    $handoff = startHandoff();

    redeemHandoff($handoff, str_repeat('x', 64))->assertUnprocessable();
});

test('a refused or failed sign-in sends the browser back to the app with the reason', function (string $email, string $query, string $error) {
    $handoff = startHandoff();
    get($handoff['url']);
    signInAs('sso-account-1', $email);

    $link = get('/auth/oauth2/callback'.$query)->assertRedirect();

    expect(appLinkFrom($link))->toBe(['handoff' => $handoff['id'], 'error' => $error]);
    redeemHandoff($handoff, 'anything')->assertUnprocessable();
})->with([
    'no matching user' => ['stranger@example.com', '?code=the-code&state=the-state', 'no_account'],
    'cancelled at the identity provider' => ['admin@invoiceshelf.com', '?error=access_denied&state=the-state', 'failed'],
]);

test('a browser signed in to the web app still finishes the app sign-in, for the SSO account', function () {
    actingAs(User::factory()->create(), 'web');
    $handoff = startHandoff();

    get($handoff['url'])->assertRedirect();
    signInAs('sso-account-1', seededAdmin()->email);
    $token = redeemHandoff($handoff, appLinkFrom(oauth2Callback())['code'])->assertOk()->json('token');

    expect(PersonalAccessToken::findToken($token)->tokenable->is(seededAdmin()))->toBeTrue();
});

test('starting the web sign-in drops a hand-off the browser abandoned', function () {
    $handoff = startHandoff();
    get($handoff['url']);

    get('/auth/oauth2/redirect')->assertRedirect();
    signInAs('sso-account-1', seededAdmin()->email);

    oauth2Callback()->assertRedirect(url('/admin/dashboard'));
    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('a hand-off left too long expires, and so does an unused code', function () {
    $handoff = startHandoff();
    $this->travel(OAuth2Handoffs::SIGN_IN_SECONDS + 1)->seconds();

    get($handoff['url'])->assertNotFound();

    $handoff = startHandoff();
    get($handoff['url']);
    signInAs('sso-account-1', seededAdmin()->email);
    $link = appLinkFrom(oauth2Callback());
    $this->travel(OAuth2Handoffs::CODE_SECONDS + 1)->seconds();

    redeemHandoff($handoff, $link['code'])->assertUnprocessable();
});

test('the client manifest tells the mobile app whether to offer sign-in with SSO', function () {
    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('oauth2_login'))->toBeTrue();

    configureOAuth2(['token_url' => null]);

    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('oauth2_login'))->toBeFalse();
});

test('the hand-off API is throttled like the password sign-in', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/auth/oauth2/'));

    expect($routes)->toHaveCount(2);

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('throttle:10,1,oauth2-handoff');
    }
});
