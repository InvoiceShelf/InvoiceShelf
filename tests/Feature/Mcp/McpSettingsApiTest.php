<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\McpSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->admin = User::where('role', 'super admin')->first();

    $this->keyDirectory = storage_path('framework/testing/mcp-keys-'.Str::random(8));
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    config(['passport.private_key' => null, 'passport.public_key' => null]);
});

afterEach(function () {
    File::deleteDirectory($this->keyDirectory);
    Passport::$keyPath = null;
});

test('only a super administrator manages the server', function () {
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($this->admin->companies()->first()->id);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/super-admin/mcp')->assertForbidden();
    $this->putJson('/api/v1/super-admin/mcp', ['enabled' => true])->assertForbidden();
});

test('the settings describe the server', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $this->getJson('/api/v1/super-admin/mcp')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.server_url', url('/mcp'))
        ->assertJsonPath('data.key_status', 'missing')
        ->assertJsonPath('data.connection_count', 0);
});

test('switching the server on creates the signing keys', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $this->putJson('/api/v1/super-admin/mcp', ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.key_status', 'file');

    expect(app(McpSettings::class)->enabled())->toBeTrue()
        ->and(File::exists($this->keyDirectory.'/oauth-private.key'))->toBeTrue();
});

test('extra redirect origins must be plain https origins', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $this->putJson('/api/v1/super-admin/mcp', ['redirect_domains' => ['https://*.example.com']])->assertUnprocessable();
    $this->putJson('/api/v1/super-admin/mcp', ['redirect_domains' => ['http://agent.example.com']])->assertUnprocessable();
    $this->putJson('/api/v1/super-admin/mcp', ['redirect_domains' => ['https://agent.example.com/callback']])->assertUnprocessable();

    $this->putJson('/api/v1/super-admin/mcp', ['redirect_domains' => ['https://agent.example.com/']])
        ->assertOk()
        ->assertJsonPath('data.redirect_domains', ['https://agent.example.com']);
});

test('regenerating the keys signs every client out', function () {
    Sanctum::actingAs($this->admin, ['*']);
    $this->putJson('/api/v1/super-admin/mcp', ['enabled' => true])->assertOk();

    Passport::token()->forceFill([
        'id' => Str::random(40), 'user_id' => $this->admin->id, 'client_id' => (string) Str::uuid(),
        'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour(),
    ])->save();

    $this->postJson('/api/v1/super-admin/mcp/keys')->assertOk();

    expect(Passport::token()->newQuery()->where('revoked', false)->count())->toBe(0);
});

test('keys set in the environment are not regenerated here', function () {
    config(['passport.private_key' => 'private', 'passport.public_key' => 'public']);
    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/v1/super-admin/mcp/keys')->assertUnprocessable();
});
