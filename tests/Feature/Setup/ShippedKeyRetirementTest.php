<?php

use App\Platform\Modules\Models\MarketplaceCredential;
use App\Platform\Operations\Application\ShippedKeyRetirement;
use App\Platform\Operations\Update\Updater;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

use function Pest\Laravel\artisan;

const SHIPPED_APP_KEY = 'base64:kgk/4DW1vEVy7aEvet5FPp5un6PIGe/so8H0mvoUtW0=';

function sealedWithShippedKey(string $plain): string
{
    return (new Encrypter(base64_decode(substr(SHIPPED_APP_KEY, 7)), config('app.cipher')))->encryptString($plain);
}

/**
 * Point the application at a scratch environment file holding the given
 * contents, and return that file's path.
 */
function scratchEnvironment(string $contents): string
{
    $directory = sys_get_temp_dir().'/shipped-key-'.uniqid();
    mkdir($directory);
    app()->useEnvironmentPath($directory);
    file_put_contents(app()->environmentFilePath(), $contents);

    return app()->environmentFilePath();
}

function removeScratchEnvironment(string $file): void
{
    unlink($file);
    rmdir(dirname($file));
}

test('a marketplace credential sealed with the shipped key is sealed again with the current one', function () {
    $credential = MarketplaceCredential::query()->create(['credential' => sealedWithShippedKey('installation-token')]);
    $own = MarketplaceCredential::query()->create(['credential' => Crypt::encryptString('already-ours')]);
    $sealed = $own->credential;

    artisan('invoiceshelf:retire-shipped-key')->assertSuccessful();

    expect(Crypt::decryptString($credential->fresh()->credential))->toBe('installation-token')
        ->and($own->fresh()->credential)->toBe($sealed);
});

test('it refuses to run on the shipped key without --rotate', function () {
    config(['app.key' => SHIPPED_APP_KEY]);
    $credential = MarketplaceCredential::query()->create(['credential' => sealedWithShippedKey('installation-token')]);
    $sealed = $credential->credential;

    artisan('invoiceshelf:retire-shipped-key')->assertFailed();

    expect($credential->fresh()->credential)->toBe($sealed);
});

test('--rotate replaces the shipped key in .env, and the process and credential follow it', function () {
    $envFile = scratchEnvironment("APP_NAME=InvoiceShelf\nAPP_KEY=".SHIPPED_APP_KEY."\nAPP_DEBUG=false\n");
    config(['app.key' => SHIPPED_APP_KEY]);
    $credential = MarketplaceCredential::query()->create(['credential' => sealedWithShippedKey('installation-token')]);

    artisan('invoiceshelf:retire-shipped-key', ['--rotate' => true])->assertSuccessful();

    $key = config('app.key');

    expect($key)->not->toBe(SHIPPED_APP_KEY)->toStartWith('base64:')
        ->and(file_get_contents($envFile))->toBe("APP_NAME=InvoiceShelf\nAPP_KEY={$key}\nAPP_DEBUG=false\n")
        ->and(Crypt::decryptString($credential->fresh()->credential))->toBe('installation-token')
        ->and((new Encrypter(base64_decode(substr($key, 7)), config('app.cipher')))->decryptString(Crypt::encryptString('sealed')))->toBe('sealed');

    removeScratchEnvironment($envFile);
});

test('--rotate leaves a shipped key alone when it does not come from .env', function () {
    $envFile = scratchEnvironment("APP_NAME=InvoiceShelf\n");
    config(['app.key' => SHIPPED_APP_KEY]);

    artisan('invoiceshelf:retire-shipped-key', ['--rotate' => true])->assertFailed();

    expect(file_get_contents($envFile))->toBe("APP_NAME=InvoiceShelf\n")
        ->and(config('app.key'))->toBe(SHIPPED_APP_KEY);

    removeScratchEnvironment($envFile);
});

test('.env.example no longer ships a key', function () {
    expect(ShippedKeyRetirement::SHIPPED_KEY)->toBe(SHIPPED_APP_KEY)
        ->and(file_get_contents(base_path('.env.example')))->toContain("\nAPP_KEY=\n")->not->toContain(SHIPPED_APP_KEY);
});

test('finishing an update moves the installation off the shipped key', function () {
    $envFile = scratchEnvironment('APP_KEY='.SHIPPED_APP_KEY."\n");
    config(['app.key' => SHIPPED_APP_KEY]);

    Updater::finishUpdate('3.0.0-alpha.3', '3.0.0-alpha.4');

    expect(config('app.key'))->not->toBe(SHIPPED_APP_KEY)
        ->and(file_get_contents($envFile))->toBe('APP_KEY='.config('app.key')."\n");

    removeScratchEnvironment($envFile);
});
