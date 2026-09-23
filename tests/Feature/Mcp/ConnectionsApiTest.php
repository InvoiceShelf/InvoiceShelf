<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::where('role', 'super admin')->first();
    $this->company = $this->user->companies()->first();
    $this->withHeader('company', (string) $this->company->id);

    $this->connectionFor = fn (User $user, string $access = McpConnection::ACCESS_WRITE) => McpConnection::query()->create([
        'user_id' => $user->id,
        'company_id' => $this->company->id,
        'oauth_client_id' => (string) Str::uuid(),
        'access' => $access,
        'client_name' => 'Claude',
        'redirect_host' => 'claude.ai',
    ]);
});

test('a user sees only their own connections', function () {
    $mine = ($this->connectionFor)($this->user);
    ($this->connectionFor)(User::factory()->create());

    Sanctum::actingAs($this->user, ['*']);

    $this->getJson('/api/v1/mcp/connections')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.client_name', 'Claude')
        ->assertJsonPath('data.0.redirect_host', 'claude.ai')
        ->assertJsonPath('data.0.company.id', $this->company->id)
        ->assertJsonPath('data.0.access', 'write');
});

test('a connection can be lowered to read only but not raised', function () {
    $connection = ($this->connectionFor)($this->user);
    Sanctum::actingAs($this->user, ['*']);

    $this->patchJson("/api/v1/mcp/connections/{$connection->id}", ['access' => 'read'])
        ->assertOk()
        ->assertJsonPath('data.access', 'read');

    $this->patchJson("/api/v1/mcp/connections/{$connection->id}", ['access' => 'write'])
        ->assertUnprocessable();
});

test('nobody else can change or revoke a connection', function () {
    $connection = ($this->connectionFor)(User::factory()->create());
    Sanctum::actingAs($this->user, ['*']);

    $this->patchJson("/api/v1/mcp/connections/{$connection->id}", ['access' => 'read'])->assertForbidden();
    $this->deleteJson("/api/v1/mcp/connections/{$connection->id}")->assertForbidden();

    expect($connection->fresh())->not->toBeNull();
});

test('revoking removes the connection and its tokens', function () {
    $connection = ($this->connectionFor)($this->user);
    Passport::token()->forceFill([
        'id' => Str::random(40), 'user_id' => $this->user->id, 'client_id' => $connection->oauth_client_id,
        'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour(),
    ])->save();

    Sanctum::actingAs($this->user, ['*']);

    $this->deleteJson("/api/v1/mcp/connections/{$connection->id}")->assertOk();

    expect($connection->fresh())->toBeNull()
        ->and(Passport::token()->newQuery()->where('revoked', false)->count())->toBe(0);
});

test('the server address is available to any signed-in user', function () {
    Sanctum::actingAs($this->user, ['*']);

    $this->getJson('/api/v1/mcp/server')
        ->assertOk()
        ->assertJsonPath('data.server_url', url('/mcp'))
        ->assertJsonPath('data.enabled', false);
});
