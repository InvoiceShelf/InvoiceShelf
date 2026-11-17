<?php

use App\Models\User;
use App\Support\OAuth2Login;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as OAuth2Account;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
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

/** What the login page passes to the Vue app about sign-in with SSO. */
function oauth2OnLoginPage(): ?array
{
    test()->withoutVite();

    if (! preg_match('/window\.oauth2_login = (\{.*\})/', (string) test()->view('app'), $match)) {
        return null;
    }

    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}

function seededAdmin(): User
{
    return User::query()->where('email', 'admin@invoiceshelf.com')->firstOrFail();
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

test('a fixed redirect URI replaces the one built from the request', function () {
    configureOAuth2(['redirect' => 'https://invoices.example.com/auth/oauth2/callback']);

    $location = get('/auth/oauth2/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://invoices.example.com/auth/oauth2/callback');
});

test('the scopes come from OAUTH2_SCOPES, separated by spaces or commas', function () {
    configureOAuth2(['scopes' => 'openid email, groups']);

    $location = get('/auth/oauth2/redirect')->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('openid email groups');
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
            'email' => 'admin@invoiceshelf.com',
            'email_verified' => false,
            'name' => 'Jane Doe',
        ])),
    ]));
    $identityProvider->push(Middleware::history($requests));
    configureOAuth2(['guzzle' => ['handler' => $identityProvider]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    oauth2Callback()->assertRedirect('/admin/dashboard');

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
    signInAs('sso-account-1', 'Admin@InvoiceShelf.com');

    oauth2Callback()->assertRedirect('/admin/dashboard');

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
    expect(seededAdmin()->oauth2_id)->toBe('sso-account-1');
});

test('a linked account signs in as its user even after an email address changed', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();
    signInAs('sso-account-1', 'new-address@example.com');

    oauth2Callback()->assertRedirect('/admin/dashboard');

    $this->assertAuthenticatedAs(seededAdmin(), 'web');
});

test('an account that matches no user is refused, and nobody is created', function () {
    $users = User::query()->count();
    signInAs('sso-account-1', 'stranger@example.com');

    oauth2Callback()
        ->assertRedirect('/login')
        ->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(User::query()->count())->toBe($users);
});

test('an account without an email address is refused', function () {
    signInAs('sso-account-1', null);

    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
});

test('an account without a subject is refused', function () {
    signInAs('', 'admin@invoiceshelf.com');

    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBeNull();
});

test('a userinfo answer without a "sub" claim is refused', function () {
    configureOAuth2(['guzzle' => ['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'the-access-token', 'token_type' => 'Bearer'])),
        new Response(200, ['Content-Type' => 'application/json'], json_encode(['email' => 'admin@invoiceshelf.com'])),
    ]))]]);

    $this->withSession(['state' => 'the-state', 'code_verifier' => 'the-code-verifier']);
    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBeNull();
});

test('a user linked to another account is not taken over by a new account with their email address', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();
    signInAs('sso-account-2', 'admin@invoiceshelf.com');

    oauth2Callback()->assertSessionHas(OAuth2Login::ERROR, 'no_account');

    $this->assertGuest('web');
    expect(seededAdmin()->oauth2_id)->toBe('sso-account-1');
});

test('a cancelled or incomplete sign-in goes back to the login page', function (string $query) {
    signInAs('sso-account-1', 'admin@invoiceshelf.com');

    get('/auth/oauth2/callback'.$query)
        ->assertRedirect('/login')
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
        ->assertRedirect('/login')
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

test('signing in returns to the page that asked for it', function () {
    signInAs('sso-account-1', 'admin@invoiceshelf.com');

    $this->withSession(['url.intended' => url('/admin/invoices')]);

    oauth2Callback()->assertRedirect('/admin/invoices');
});

test('a user who is signed in already goes straight to the dashboard', function () {
    actingAs(seededAdmin());

    get('/auth/oauth2/redirect')->assertRedirect('/admin/dashboard');
});

test('oauth2:unlink removes the link, so the next sign-in links by email address again', function () {
    seededAdmin()->forceFill(['oauth2_id' => 'sso-account-1'])->save();

    artisan('oauth2:unlink', ['email' => 'ADMIN@invoiceshelf.com'])
        ->expectsOutputToContain('Unlinked admin@invoiceshelf.com')
        ->assertSuccessful();

    expect(seededAdmin()->oauth2_id)->toBeNull();

    signInAs('sso-account-2', 'admin@invoiceshelf.com');
    oauth2Callback()->assertRedirect('/admin/dashboard');

    expect(seededAdmin()->oauth2_id)->toBe('sso-account-2');
});

test('oauth2:unlink fails for an unknown email address', function () {
    artisan('oauth2:unlink', ['email' => 'nobody@example.com'])->assertFailed();
});

test('the profile and user APIs cannot link a user to an SSO account', function () {
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

    putJson("/api/v1/users/{$colleague->id}", [
        'name' => $colleague->name,
        'email' => $colleague->email,
        'companies' => [['id' => $companyId, 'role' => 'super admin']],
        'oauth2_id' => 'chosen-by-the-caller',
    ])->assertOk();

    expect(User::query()->whereNotNull('oauth2_id')->exists())->toBeFalse();
});
