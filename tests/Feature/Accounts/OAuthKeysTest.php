<?php

use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = storage_path('framework/testing/oauth-keys-'.Str::random(8));
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);

    config(['passport.private_key' => null, 'passport.public_key' => null]);
});

afterEach(function () {
    File::deleteDirectory($this->keyDirectory);
    Passport::$keyPath = null;
});

test('keys are missing until created', function () {
    expect(app(OAuthKeyManager::class)->status())->toBe(OAuthKeyManager::SOURCE_MISSING);
});

test('keys from the environment take precedence and are never written', function () {
    config(['passport.private_key' => 'private', 'passport.public_key' => 'public']);

    $this->artisan('oauth:keys')->assertSuccessful();

    expect(app(OAuthKeyManager::class)->status())->toBe(OAuthKeyManager::SOURCE_ENV)
        ->and(File::exists($this->keyDirectory.'/oauth-private.key'))->toBeFalse();
});

test('if-missing creates the pair once and then does nothing', function () {
    $this->artisan('oauth:keys --if-missing')->assertSuccessful();

    $private = $this->keyDirectory.'/oauth-private.key';
    $written = File::get($private);

    expect(app(OAuthKeyManager::class)->status())->toBe(OAuthKeyManager::SOURCE_FILE)
        ->and(substr(sprintf('%o', fileperms($private)), -4))->toBe('0600');

    $this->artisan('oauth:keys --if-missing')->assertSuccessful();

    expect(File::get($private))->toBe($written);
});

test('existing keys are only replaced with force, which signs every client out', function () {
    app(OAuthKeyManager::class)->ensure();

    $this->artisan('oauth:keys')->assertFailed();

    Passport::token()->forceFill([
        'id' => Str::random(40), 'user_id' => User::factory()->create()->id,
        'client_id' => (string) Str::uuid(), 'scopes' => [], 'revoked' => false,
        'expires_at' => now()->addHour(),
    ])->save();

    $this->artisan('oauth:keys --force')->assertSuccessful();

    expect(Passport::token()->newQuery()->where('revoked', false)->count())->toBe(0);
});
