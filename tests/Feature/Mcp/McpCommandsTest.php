<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = storage_path('framework/testing/mcp-cli-keys-'.Str::random(8));
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    config(['passport.private_key' => null, 'passport.public_key' => null]);
});

afterEach(function () {
    File::deleteDirectory($this->keyDirectory);
    Passport::$keyPath = null;
});

test('mcp:enable switches the server on and creates keys', function () {
    $this->artisan('mcp:enable')->assertSuccessful();

    expect(app(McpSettings::class)->enabled())->toBeTrue()
        ->and(File::exists($this->keyDirectory.'/oauth-private.key'))->toBeTrue();

    $this->artisan('mcp:disable')->assertSuccessful();

    expect(app(McpSettings::class)->enabled())->toBeFalse();
});

test('mcp:prune removes old registrations that never led anywhere', function () {
    $clients = app(ClientRepository::class);
    $unused = $clients->createAuthorizationCodeGrantClient('Unused', ['https://claude.ai/cb'], false);
    $connected = $clients->createAuthorizationCodeGrantClient('Connected', ['https://claude.ai/cb'], false);
    $fresh = $clients->createAuthorizationCodeGrantClient('Fresh', ['https://claude.ai/cb'], false);

    Passport::client()->newQuery()->whereKey([$unused->getKey(), $connected->getKey()])->update(['created_at' => now()->subDays(2)]);

    McpConnection::query()->create([
        'user_id' => User::factory()->create()->id,
        'company_id' => 1,
        'oauth_client_id' => $connected->getKey(),
        'access' => McpConnection::ACCESS_READ,
    ]);

    $this->artisan('mcp:prune')->assertSuccessful();

    expect(Passport::client()->newQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$connected->getKey(), $fresh->getKey()]);
});
