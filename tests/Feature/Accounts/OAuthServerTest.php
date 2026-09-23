<?php

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Guards\TokenGuard;
use Laravel\Passport\Passport;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\OAuthTesting;

beforeEach(function () {
    // Currencies come from the catalogue now, not from migrations, and the
    // user factory needs one.
    Artisan::call('db:seed', ['--class' => 'CurrenciesTableSeeder', '--force' => true]);

    OAuthTesting::useKeys();

    Passport::tokensCan(['test:use' => 'Use the test consumer']);
    Passport::authorizationView(fn (array $parameters) => response('consent for '.$parameters['client']->name));

    Route::middleware('auth:oauth')->get('/_oauth-test/whoami', function (Request $request) {
        $user = $request->user();

        return [
            'id' => $user->id,
            'user_class' => $user::class,
            'token_class' => $user->currentAccessToken()::class,
            'can_test' => $user->tokenCan('test:use'),
        ];
    });

    Route::middleware(['auth:oauth', 'company', 'bouncer'])->get('/_oauth-test/abilities', function (Request $request) {
        return ['view_invoices' => $request->user()->can('view-invoice', Invoice::class)];
    });
});

test('the oauth routes answer 404 while no consumer is switched on', function () {
    $this->get('/oauth/authorize')->assertNotFound();
    $this->post('/oauth/token')->assertNotFound();
});

test('the oauth guard is a passport guard over the users provider', function () {
    expect(config('auth.guards.oauth'))->toBe(['driver' => 'passport', 'provider' => 'users'])
        ->and(auth()->guard('oauth'))->toBeInstanceOf(TokenGuard::class);
});

test('a token from the authorization code flow authenticates on the oauth guard', function () {
    OAuthTesting::enableServer();
    $user = User::factory()->create();

    $tokens = OAuthTesting::authorize($this, $user, OAuthTesting::publicClient(), 'test:use');

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token', 'expires_in'])
        ->and($tokens['expires_in'])->toBeLessThanOrEqual(3600);

    $this->withToken($tokens['access_token'])
        ->getJson('/_oauth-test/whoami')
        ->assertOk()
        ->assertJson([
            'id' => $user->id,
            'user_class' => User::class,
            'token_class' => AccessToken::class,
            'can_test' => true,
        ]);
});

test('a guest is sent to sign in and back to the consent screen', function () {
    OAuthTesting::enableServer();
    $client = OAuthTesting::publicClient();

    $query = http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => OAuthTesting::REDIRECT_URI,
        'response_type' => 'code',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $response = $this->get('/oauth/authorize?'.$query);

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $loginQuery);

    expect(parse_url($location, PHP_URL_PATH))->toBe('/login')
        ->and($loginQuery['next'])->toStartWith('/oauth/authorize?')
        ->and($loginQuery['next'])->toContain('client_id='.$client->getKey());
});

test('sanctum and oauth tokens are not accepted by each other', function () {
    OAuthTesting::enableServer();
    $user = User::factory()->create();

    $sanctum = $user->createToken('device')->plainTextToken;
    $this->withToken($sanctum)->getJson('/_oauth-test/whoami')->assertUnauthorized();

    app('auth')->forgetGuards();
    $tokens = OAuthTesting::authorize($this, $user, OAuthTesting::publicClient(), 'test:use');

    $this->withToken($tokens['access_token'])->getJson('/api/v1/auth/check')->assertUnauthorized();
});

test('sanctum personal access tokens are unchanged', function () {
    $user = User::factory()->create();

    expect($user->createToken('device'))->toBeInstanceOf(NewAccessToken::class)
        ->and($user->tokens()->getRelated())->toBeInstanceOf(PersonalAccessToken::class);
});

test('bouncer answers for a member authenticated through the oauth guard', function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    OAuthTesting::enableServer();

    $user = User::where('role', 'super admin')->first();
    $tokens = OAuthTesting::authorize($this, $user, OAuthTesting::publicClient(), 'test:use');

    $this->withToken($tokens['access_token'])
        ->withHeader('company', (string) $user->companies()->first()->id)
        ->getJson('/_oauth-test/abilities')
        ->assertOk()
        ->assertJson(['view_invoices' => true]);
});

test('a revoked grant stops working on the next request', function () {
    OAuthTesting::enableServer();
    $user = User::factory()->create();
    $client = OAuthTesting::publicClient();
    $tokens = OAuthTesting::authorize($this, $user, $client, 'test:use');

    $this->withToken($tokens['access_token'])->getJson('/_oauth-test/whoami')->assertOk();

    app(AccessRevoker::class)->revokeClient($user->id, $client->getKey());
    app('auth')->forgetGuards();

    $this->withToken($tokens['access_token'])->getJson('/_oauth-test/whoami')->assertUnauthorized();

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->getKey(),
    ])->assertStatus(400);
});

test('refreshing keeps the same user and client', function () {
    OAuthTesting::enableServer();
    $user = User::factory()->create();
    $client = OAuthTesting::publicClient();
    $tokens = OAuthTesting::authorize($this, $user, $client, 'test:use');

    $refreshed = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->getKey(),
    ])->assertOk()->json();

    $token = Passport::token()->newQuery()->latest('created_at')->first();

    expect($refreshed['access_token'])->not->toBe($tokens['access_token'])
        ->and($token->user_id)->toBe($user->id)
        ->and($token->client_id)->toBe($client->getKey());

    app('auth')->forgetGuards();
    $this->withToken($refreshed['access_token'])->getJson('/_oauth-test/whoami')->assertOk();
});

test('the device code grant is switched off', function () {
    expect(Passport::$deviceCodeGrantEnabled)->toBeFalse()
        ->and(Route::has('passport.device'))->toBeFalse();
});
