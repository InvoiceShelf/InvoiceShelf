<?php

use App\Platform\Modules\Marketplace\CanonicalJson;
use App\Platform\Modules\Marketplace\MarketplaceInstaller;
use App\Platform\Modules\Marketplace\MarketplaceUninstaller;
use App\Platform\Modules\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    File::deleteDirectory(base_path('Modules/SecureProbe'));
    File::deleteDirectory(base_path('Modules/.staging'));
    File::deleteDirectory(base_path('Modules/.backups'));
});

it('installs an exact signed marketplace archive', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeTrue()
        ->and(base_path('Modules/SecureProbe/module.json'))->toBeFile()
        ->and(Module::query()->where('name', 'SecureProbe')->value('version'))->toBe('1.0.0');
});

it('uninstalls a signed module and refreshes the runtime', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    expect(app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable')['success'])->toBeTrue();

    $result = app(MarketplaceUninstaller::class)->uninstall('SecureProbe', false);

    expect($result['success'])->toBeTrue()
        ->and(base_path('Modules/SecureProbe'))->not->toBeDirectory()
        ->and(Module::query()->where('name', 'SecureProbe')->value('installed'))->toBeFalse();
});

it('reinstalls the same version after a code-only uninstall record', function () {
    Module::query()->create([
        'name' => 'SecureProbe',
        'slug' => 'secure-probe',
        'version' => '1.0.0',
        'installed' => false,
        'enabled' => false,
        'state' => 'uninstalled',
    ]);
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeTrue()
        ->and(Module::query()->where('name', 'SecureProbe')->value('installed'))->toBeTrue()
        ->and(Module::query()->where('name', 'SecureProbe')->value('version'))->toBe('1.0.0');
});

it('rejects reinstalling the same version while it remains installed', function () {
    Module::query()->create([
        'name' => 'SecureProbe',
        'slug' => 'secure-probe',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => false,
        'state' => 'installed',
    ]);
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('reinstalling the same release');
});

it('rejects a release signed by an unknown key before downloading an artifact', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair);
    config()->set('invoiceshelf.marketplace.public_keys', ['other-key' => base64_encode(sodium_crypto_sign_publickey($keypair))]);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()->and($result['error'])->toContain('unknown signing key');
    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://artifacts.test/'));
});

it('rejects envelope integrity metadata that differs from the signed manifest', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease();
    fakeMarketplaceRelease($archive, $manifest, $keypair, ['bytes' => strlen($archive) + 1]);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()->and($result['error'])->toContain('integrity fields');
});

it('rejects missing required PHP extensions before downloading an artifact', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease(['compatibility' => [
        'invoiceshelf' => '^3.0.0', 'module_api' => '^1.0.0', 'php' => '^8.4.0', 'extensions' => ['ext-no-such-extension'],
    ]]);
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()->and($result['error'])->toContain('extension');
    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://artifacts.test/'));
});

it('rejects path traversal archives even with valid signed integrity metadata', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease([], ['SecureProbe/../escape.php' => 'unsafe']);
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()->and($result['error'])->toContain('unsafe path')
        ->and(base_path('Modules/SecureProbe'))->not->toBeDirectory();
});

it('restores installation state when a module migration fails', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease([], [
        'SecureProbe/database/migrations/2026_08_05_000000_fail_probe.php' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('probe migration failed');
    }

    public function down(): void {}
};
PHP,
    ]);
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()
        ->and(base_path('Modules/SecureProbe'))->not->toBeDirectory()
        ->and(Module::query()->where('name', 'SecureProbe')->value('state'))->toBe('failed');
});

it('rejects package composer metadata that does not match the official module contract', function () {
    [$archive, $manifest, $keypair] = marketplaceRelease([], [], ['license' => 'MIT']);
    fakeMarketplaceRelease($archive, $manifest, $keypair);

    $result = app(MarketplaceInstaller::class)->install('secure-probe', '1.0.0', 'stable');

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('composer manifest');
});

/** @return array{string, array<string, mixed>, string} */
function marketplaceRelease(array $changes = [], array $extraEntries = [], array $composerChanges = []): array
{
    config()->set('app.version', '3.0.0');
    config()->set('invoiceshelf.marketplace.module_api_version', '1.0.0');
    $zipPath = tempnam(sys_get_temp_dir(), 'marketplace-test-');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $module = [
        'name' => 'SecureProbe', 'alias' => 'secure_probe', 'description' => 'Secure test module', 'keywords' => [], 'priority' => 0,
        'providers' => ['Modules\\SecureProbe\\Providers\\SecureProbeServiceProvider'], 'aliases' => [], 'files' => [], 'requires' => [],
        'schema_version' => 1, 'slug' => 'secure-probe', 'version' => '1.0.0', 'license' => 'AGPL-3.0-only',
        'compatibility' => ['invoiceshelf' => '^3.0.0', 'module_api' => '^1.0.0', 'php' => '^8.4.0', 'extensions' => []],
        'module_dependencies' => [], 'migration_policy' => 'forward-only', 'dependency_policy' => 'host-provided-only', 'assets' => ['dist/app.js'],
    ];
    $zip->addFromString('SecureProbe/module.json', json_encode($module, JSON_THROW_ON_ERROR));
    $zip->addFromString('SecureProbe/composer.json', json_encode([
        'name' => 'invoiceshelf/module-secure-probe',
        'license' => 'AGPL-3.0-only',
        'require' => ['php' => '^8.4', 'invoiceshelf/modules' => '^3.0'],
        ...$composerChanges,
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('SecureProbe/dist/app.js', 'export {}');
    $zip->addFromString('SecureProbe/app/Providers/SecureProbeServiceProvider.php', <<<'PHP'
<?php

namespace Modules\SecureProbe\Providers;

use Illuminate\Support\ServiceProvider;

class SecureProbeServiceProvider extends ServiceProvider {}
PHP);
    foreach ($extraEntries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
    $archive = (string) file_get_contents($zipPath);
    unlink($zipPath);

    $keypair = sodium_crypto_sign_keypair();
    $manifest = [
        'schema_version' => 1, 'slug' => 'secure-probe', 'module_name' => 'SecureProbe', 'version' => '1.0.0',
        'channel' => 'stable', 'publication' => 'published', 'compatibility' => $module['compatibility'],
        'artifact' => ['sha256' => hash('sha256', $archive), 'bytes' => strlen($archive)], 'key_id' => 'test-key',
        'source_commit' => str_repeat('a', 40), 'released_at' => '2026-08-05T12:00:00Z',
    ];
    foreach ($changes as $key => $value) {
        $manifest[$key] = $value;
    }

    return [$archive, $manifest, $keypair];
}

function fakeMarketplaceRelease(string $archive, array $manifest, string $keypair, array $artifactChanges = []): void
{
    config()->set('invoiceshelf.base_url', 'https://marketplace.test');
    config()->set('invoiceshelf.marketplace.public_keys', ['test-key' => base64_encode(sodium_crypto_sign_publickey($keypair))]);
    $artifact = [...$manifest['artifact'], 'download_url' => 'https://artifacts.test/secure-probe.zip', 'expires_at' => now()->addMinute()->toIso8601String(), ...$artifactChanges];
    $envelope = [
        'success' => true, 'manifest' => $manifest,
        'signature' => base64_encode(sodium_crypto_sign_detached(CanonicalJson::encode($manifest), sodium_crypto_sign_secretkey($keypair))),
        'key_id' => 'test-key', 'release_state' => 'published', 'yanked_reason' => null, 'artifact' => $artifact,
    ];
    Http::fake([
        'https://marketplace.test/api/marketplace/v1/modules/secure-probe/releases/1.0.0/download' => Http::response($envelope),
        'https://artifacts.test/*' => Http::response($archive),
    ]);
}
