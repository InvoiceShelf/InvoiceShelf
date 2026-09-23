<?php

use App\Domains\Accounts\Application\MemberService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Tests\Support\McpTesting;
use Tests\Support\OAuthTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::where('role', 'super admin')->first();
    $this->company = $this->user->companies()->first();
});

test('everything answers 404 while the server is off', function () {
    OAuthTesting::useKeys();

    $this->postJson('/mcp', [])->assertNotFound();
    $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertNotFound();
    $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
    $this->postJson('/oauth/register', ['redirect_uris' => ['https://claude.ai/cb']])->assertNotFound();
    $this->get('/oauth/authorize')->assertNotFound();
    $this->post('/oauth/token')->assertNotFound();
});

test('the discovery documents describe the server', function () {
    McpTesting::enable();

    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJson([
            'resource' => url('/mcp'),
            'authorization_servers' => [url('/')],
            'scopes_supported' => ['mcp:use'],
        ]);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJson([
            'issuer' => url('/'),
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'registration_endpoint' => url('/oauth/register'),
            'code_challenge_methods_supported' => ['S256'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
        ]);
});

test('an unauthenticated call is pointed at the metadata', function () {
    McpTesting::enable();

    $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

test('registration accepts known redirect origins and refuses the rest', function (string $uri, bool $accepted) {
    McpTesting::enable();

    $response = $this->postJson('/oauth/register', ['client_name' => 'Client', 'redirect_uris' => [$uri]]);

    $accepted ? $response->assertCreated() : $response->assertStatus(400);
})->with([
    'claude.ai' => ['https://claude.ai/api/mcp/auth_callback', true],
    'chatgpt' => ['https://chatgpt.com/connector_platform_oauth_redirect', true],
    'loopback, any port' => ['http://localhost:61234/callback', true],
    'loopback address' => ['http://127.0.0.1:6274/oauth/callback', true],
    'cursor' => ['cursor://anysphere.cursor-retrieval/oauth/callback', true],
    'unknown host' => ['https://evil.example/callback', false],
    'lookalike host' => ['https://claude.ai.evil.example/callback', false],
    'plain http elsewhere' => ['http://evil.example/callback', false],
    'unknown scheme' => ['javascript://alert/1', false],
]);

test('an administrator can allow another origin, never a wildcard', function () {
    McpTesting::enable();
    app(McpSettings::class)->setExtraRedirectDomains(['https://agent.example.com', '*']);

    $this->postJson('/oauth/register', ['redirect_uris' => ['https://agent.example.com/callback']])->assertCreated();
    $this->postJson('/oauth/register', ['redirect_uris' => ['https://anything.example/callback']])->assertStatus(400);
});

test('the consent screen leads with the redirect host and offers the user\'s companies', function () {
    McpTesting::enable();
    $client = McpTesting::register($this, 'https://claude.ai/api/mcp/auth_callback', 'Claude');

    $this->actingAs($this->user, 'web')
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Connect Claude')
        ->assertSee('claude.ai')
        ->assertSee($this->company->name)
        ->assertSee('Read only');
});

test('a connection works end to end in the company it was granted', function () {
    McpTesting::enable();
    $connected = McpTesting::connect($this, $this->user, $this->company->id);
    $token = $connected['tokens']['access_token'];

    McpTesting::rpc($this, $token, 'initialize', [
        'protocolVersion' => McpTesting::PROTOCOL,
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'test', 'version' => '1'],
    ])->assertOk()->assertJsonPath('result.serverInfo.name', 'InvoiceShelf');

    $tools = McpTesting::rpc($this, $token, 'tools/list')->assertOk()->json('result.tools');
    expect(collect($tools)->pluck('name'))->toContain('get_company_context');

    McpTesting::rpc($this, $token, 'tools/call', ['name' => 'get_company_context', 'arguments' => (object) []])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.company.id', $this->company->id)
        ->assertJsonPath('result.structuredContent.connection.access', 'write');

    $connection = McpConnection::query()->sole();
    expect($connection->company_id)->toBe($this->company->id)
        ->and($connection->oauth_client_id)->toBe($connected['client']->getKey())
        ->and($connection->last_used_at)->not->toBeNull();
});

test('the company header a client sends is ignored', function () {
    McpTesting::enable();
    $other = Company::factory()->create();
    $this->user->companies()->attach($other->id);

    $token = McpTesting::connect($this, $this->user, $this->company->id)['tokens']['access_token'];

    McpTesting::rpc($this, $token, 'tools/call', ['name' => 'get_company_context', 'arguments' => (object) []], ['company' => (string) $other->id])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.company.id', $this->company->id);
});

test('approving a company the user does not belong to is refused', function () {
    McpTesting::enable();
    $foreign = Company::factory()->create();
    $client = McpTesting::register($this, 'https://claude.ai/api/mcp/auth_callback');

    $this->actingAs($this->user, 'web')->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    $this->post('/oauth/authorize', [
        'state' => 'abc',
        'client_id' => $client->getKey(),
        'auth_token' => session('authToken'),
        'company_id' => $foreign->id,
        'access' => 'write',
    ])->assertForbidden();

    expect(McpConnection::query()->count())->toBe(0);
});

test('a connection ends when its user is removed from the company', function () {
    McpTesting::enable();
    $member = User::factory()->create();
    $member->companies()->attach($this->company->id);
    $second = Company::factory()->create();
    $member->companies()->attach($second->id);

    $token = McpTesting::connect($this, $member, $second->id)['tokens']['access_token'];

    // The caller manages both companies, so leaving the second off detaches it.
    app(MemberService::class)->update($member, [], [['id' => $this->company->id, 'role' => 'owner']], [$this->company->id, $second->id]);

    expect(McpConnection::query()->count())->toBe(0);
    McpTesting::rpc($this, $token, 'tools/list')->assertUnauthorized();
});

test('a connection whose membership disappeared is refused and revoked on use', function () {
    McpTesting::enable();
    $member = User::factory()->create();
    $member->companies()->attach($this->company->id);
    $connected = McpTesting::connect($this, $member, $this->company->id);

    // Detached without going through MemberService, so no event fired.
    $member->companies()->detach($this->company->id);

    McpTesting::rpc($this, $connected['tokens']['access_token'], 'tools/list')->assertUnauthorized();

    expect(Passport::token()->newQuery()->where('client_id', $connected['client']->getKey())->where('revoked', false)->count())->toBe(0);
});

test('revoking from connected apps signs the client out', function () {
    McpTesting::enable();
    $token = McpTesting::connect($this, $this->user, $this->company->id)['tokens']['access_token'];
    $connection = McpConnection::query()->sole();

    Sanctum::actingAs($this->user, ['*']);
    $this->withHeader('company', (string) $this->company->id)
        ->deleteJson("/api/v1/mcp/connections/{$connection->id}")
        ->assertOk();

    McpTesting::rpc($this, $token, 'tools/list')->assertUnauthorized();
});

test('asking again skips the consent screen only while the connection is live', function () {
    McpTesting::enable();
    $connected = McpTesting::connect($this, $this->user, $this->company->id);
    $query = http_build_query([
        'client_id' => $connected['client']->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'again',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs($this->user, 'web')->get('/oauth/authorize?'.$query)
        ->assertRedirect()
        ->assertRedirectContains('https://claude.ai/api/mcp/auth_callback?code=');

    app(ConnectionService::class)->revoke(McpConnection::query()->sole());

    $this->actingAs($this->user, 'web')->get('/oauth/authorize?'.$query)->assertOk()->assertSee('Allow access');
});

test('binding a client to another company revokes what it held before', function () {
    McpTesting::enable();
    $other = Company::factory()->create();
    $this->user->companies()->attach($other->id);
    $connected = McpTesting::connect($this, $this->user, $this->company->id);

    app(ConnectionService::class)->bind($this->user, $connected['client'], $other->id, McpConnection::ACCESS_READ);

    McpTesting::rpc($this, $connected['tokens']['access_token'], 'tools/list')->assertUnauthorized();
    expect(McpConnection::query()->sole()->company_id)->toBe($other->id);
});

test('the server answers 503 when switched on without signing keys', function () {
    app(McpSettings::class)->setEnabled(true);
    config(['passport.private_key' => null, 'passport.public_key' => null]);
    $directory = storage_path('framework/testing/empty-keys-'.Str::random(6));
    File::ensureDirectoryExists($directory);
    Passport::loadKeysFrom($directory);

    $this->postJson('/mcp', [])->assertStatus(503)->assertJsonPath('error', 'temporarily_unavailable');
    $this->post('/oauth/token')->assertStatus(503);

    File::deleteDirectory($directory);
    Passport::$keyPath = null;
});

test('the consent screen reads in the direction of the user\'s language', function () {
    McpTesting::enable();
    config(['invoiceshelf.rtl_languages' => ['ar', 'fa', 'he', 'ur']]);
    $client = McpTesting::register($this, 'https://claude.ai/api/mcp/auth_callback');
    $query = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs($this->user, 'web')->get($query)->assertSee('dir="ltr"', false);

    $this->user->setSettings(['language' => 'ar']);

    $this->actingAs($this->user, 'web')->get($query)->assertSee('lang="ar" dir="rtl"', false);
});
