<?php

use App\Platform\Modules\Runtime\ModuleAssetVersion;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\File;
use InvoiceShelf\Modules\Registry;

use function Pest\Laravel\getJson;

/**
 * The manifest is what a thin client reads instead of the Blade shell, before
 * it holds a token. Everything asserted here has a counterpart in
 * resources/views/app.blade.php, and the two are one contract.
 */
beforeEach(function () {
    // The host's own modules would otherwise decide what this endpoint
    // answers; every module in these tests is one the test put there.
    Registry::flush();

    $this->assetDirectory = storage_path('app/client-manifest-test');
    File::ensureDirectoryExists($this->assetDirectory);
});

afterEach(function () {
    Registry::flush();
    File::deleteDirectory($this->assetDirectory);
});

test('the manifest is public and describes the running build', function () {
    $response = getJson('/api/v1/app/client-manifest')->assertOk();

    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'version', 'min_client_version', 'app_url', 'page_title', 'branding', 'modules', 'demo_mode',
    ])
        ->and($response->json('version'))->toBe(trim(File::get(base_path('version.md'))))
        ->and($response->json('min_client_version'))->toBe(config('invoiceshelf.client.min_version'))
        ->and($response->json('app_url'))->toBe(config('app.url'))
        ->and($response->json('page_title'))->toBe(get_page_title(null))
        ->and($response->json('modules'))->toBe([])
        ->and($response->json('demo_mode'))->toBeFalse()
        ->and(array_keys($response->json('branding')))->toEqualCanonicalizing([
            'login_page_logo', 'login_page_heading', 'login_page_description', 'copyright_text',
        ])
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=60');
});

test('a module publishes absolute, content-versioned asset URLs', function () {
    $script = $this->assetDirectory.'/manifest-probe.js';
    $style = $this->assetDirectory.'/manifest-probe.css';
    File::put($script, 'export const probe = true;');
    File::put($style, '.probe { color: red; }');
    Registry::registerScript('manifest-probe', $script);
    Registry::registerStyle('manifest-probe', $style);

    $modules = getJson('/api/v1/app/client-manifest')->assertOk()->json('modules');

    expect($modules)->toHaveCount(1)
        ->and($modules[0]['name'])->toBe('manifest-probe')
        ->and($modules[0]['script'])->toBe(url('/modules/scripts/manifest-probe').'?v='.ModuleAssetVersion::forPath($script))
        ->and($modules[0]['style'])->toBe(url('/modules/styles/manifest-probe').'?v='.ModuleAssetVersion::forPath($style))
        ->and($modules[0]['supported'])->toBeTrue()
        // Nothing on disk claims this asset name, so the version is unknown.
        ->and($modules[0]['version'])->toBeNull();
});

test('a module version is resolved from the installed module that owns the asset name', function () {
    // A module tree of this test's own, so nothing depends on what happens to
    // be installed in the checkout.
    $moduleDirectory = $this->assetDirectory.'/ManifestProbeModule';
    File::ensureDirectoryExists($moduleDirectory);
    File::put($moduleDirectory.'/module.json', json_encode([
        'name' => 'ManifestProbeModule',
        'alias' => 'manifestprobemodule',
        'slug' => 'manifest-probe',
        'version' => '4.2.0',
        'providers' => [],
    ]));
    config(['modules.paths.modules' => $this->assetDirectory]);

    $script = $this->assetDirectory.'/manifest-probe.js';
    File::put($script, 'export const probe = true;');
    Registry::registerScript('manifest-probe', $script);

    $modules = getJson('/api/v1/app/client-manifest')->assertOk()->json('modules');

    expect($modules)->toHaveCount(1)
        ->and($modules[0]['version'])->toBe('4.2.0');
});

test('a remotely hosted module script is handed over unchanged and flagged unsupported', function () {
    // Registry::registerScript() only accepts local files, so a remote entry
    // is written the way a host that already holds one would carry it.
    Registry::$scripts['remote-probe'] = 'https://cdn.example.com/remote-probe.js';

    $modules = getJson('/api/v1/app/client-manifest')->assertOk()->json('modules');

    expect($modules)->toHaveCount(1)
        ->and($modules[0]['script'])->toBe('https://cdn.example.com/remote-probe.js')
        ->and($modules[0]['style'])->toBeNull()
        // The operator cannot add a third-party origin to their own CORS
        // allow-list, so no client can load it.
        ->and($modules[0]['supported'])->toBeFalse();
});

test('a module that registers only a style still appears', function () {
    $style = $this->assetDirectory.'/style-only.css';
    File::put($style, '.style-only { color: blue; }');
    Registry::registerStyle('style-only', $style);

    $modules = getJson('/api/v1/app/client-manifest')->assertOk()->json('modules');

    expect($modules)->toHaveCount(1)
        ->and($modules[0]['name'])->toBe('style-only')
        ->and($modules[0]['script'])->toBeNull()
        ->and($modules[0]['style'])->toBe(url('/modules/styles/style-only').'?v='.ModuleAssetVersion::forPath($style))
        ->and($modules[0]['supported'])->toBeTrue();
});

test('branding follows the instance settings and answers null when unset', function () {
    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('branding'))->toBe([
        'login_page_logo' => null,
        'login_page_heading' => null,
        'login_page_description' => null,
        'copyright_text' => null,
    ]);

    Setting::setSettings([
        'login_page_logo' => 'branding/logo.png',
        'login_page_heading' => 'Welcome back',
        'login_page_description' => 'Sign in to keep invoicing.',
        'copyright_text' => 'Acme Inc.',
    ]);

    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('branding'))->toBe([
        // Absolute, because a client has no origin of its own to resolve
        // "/storage/..." against.
        'login_page_logo' => url('/storage/branding/logo.png'),
        'login_page_heading' => 'Welcome back',
        'login_page_description' => 'Sign in to keep invoicing.',
        'copyright_text' => 'Acme Inc.',
    ]);
});

test('the minimum client version follows configuration', function () {
    config(['invoiceshelf.client.min_version' => '9.9.9']);

    expect(getJson('/api/v1/app/client-manifest')->assertOk()->json('min_client_version'))->toBe('9.9.9');
});
