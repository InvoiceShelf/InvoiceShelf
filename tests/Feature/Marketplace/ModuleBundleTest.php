<?php

use App\Platform\Modules\Runtime\ModuleBundle;
use Illuminate\Support\Facades\File;
use Tests\Support\ModuleBundleFixture;

/*
 * Whether a bundled module's bundle/ fits this host: the host packages it
 * relies on, the PHP its packages need, and anything it ships that the host
 * or another module already provides.
 */

afterEach(function () {
    foreach (['BundleProbe', 'BundleOther'] as $name) {
        File::deleteDirectory(base_path("Modules/{$name}"));
    }
});

function bundleProbe(array $packages, array $hostPackages = [], array $files = [], string $name = 'BundleProbe'): string
{
    $path = base_path("Modules/{$name}");
    ModuleBundleFixture::write($path, $packages, $hostPackages, $files);

    return $path;
}

it('accepts a bundle whose host packages and PHP fit', function () {
    $path = bundleProbe(
        ['sample/greeter' => ['classes' => ['Sample\\Greeter\\Greeter'], 'php' => '>=8.1']],
        ['laravel/framework' => ['sample/greeter' => '^13.0'], 'guzzlehttp/guzzle' => ['invoiceshelf/module-bundle-probe' => '^6.0 || ^7.0']],
    );

    expect(ModuleBundle::problemsFor($path))->toBe([]);
});

it('ignores modules that do not bundle dependencies', function () {
    $path = bundleProbe(['sample/greeter' => []]);
    $manifest = json_decode(File::get($path.'/module.json'), true);
    File::put($path.'/module.json', json_encode([...$manifest, 'dependency_policy' => 'host-provided-only']));
    File::deleteDirectory($path.'/bundle');

    expect(ModuleBundle::problemsFor($path))->toBe([]);
});

it('refuses a host package at a version the bundle does not accept', function () {
    $path = bundleProbe(['sample/greeter' => []], ['laravel/framework' => ['sample/greeter' => '^9.0']]);

    expect(ModuleBundle::problemsFor($path))->toHaveCount(1)
        ->and(ModuleBundle::problemsFor($path)[0])->toContain('sample/greeter needs laravel/framework ^9.0, but this InvoiceShelf has');
});

it('refuses a host package this InvoiceShelf does not ship', function () {
    $path = bundleProbe(['sample/greeter' => []], ['sample/not-in-the-host' => ['sample/greeter' => '^1.0']]);

    expect(ModuleBundle::problemsFor($path))->toBe(['It needs sample/not-in-the-host, which this InvoiceShelf does not ship.']);
});

it('refuses a bundled package that needs another PHP', function () {
    $path = bundleProbe(['sample/greeter' => ['php' => '<8.0']]);

    expect(ModuleBundle::problemsFor($path))->toBe(['sample/greeter needs PHP <8.0.']);
});

it('refuses a package or class the host already ships', function () {
    $path = bundleProbe(['symfony/finder' => ['classes' => ['Symfony\\Component\\Finder\\Finder']]]);

    expect(ModuleBundle::problemsFor($path))->toBe([
        'symfony/finder is bundled, but InvoiceShelf already ships it.',
        'The bundle ships Symfony\\Component\\Finder\\Finder, which InvoiceShelf already has.',
    ]);
});

it('refuses a function the host already defines', function () {
    $path = bundleProbe(['sample/helpers' => []], files: ['sample/helpers/src/functions.php' => "<?php\n\nfunction app() {}\n"]);

    expect(ModuleBundle::problemsFor($path))->toBe(['The bundle declares app(), which already exists.']);
});

it('allows another module to bundle the same release of a package, but not a different one', function () {
    $same = ['sample/greeter' => ['version' => '1.0.0', 'classes' => ['Sample\\Greeter\\Greeter']]];
    bundleProbe($same, name: 'BundleOther');
    $path = bundleProbe($same);

    expect(ModuleBundle::problemsFor($path))->toBe([]);

    bundleProbe(['sample/greeter' => ['version' => '2.0.0', 'classes' => ['Sample\\Greeter\\Greeter']]], name: 'BundleOther');

    expect(ModuleBundle::problemsFor($path))->toBe([
        'sample/greeter 1.0.0 is bundled, but BundleOther bundles 2.0.0.',
        'The bundle ships Sample\\Greeter\\Greeter, which BundleOther also bundles.',
    ]);
});

it('refuses classes and functions another module bundles from a different package', function () {
    bundleProbe(['sample/one' => ['classes' => ['Shared\\Thing']]], files: ['sample/one/src/f.php' => "<?php\n\nnamespace Shared;\n\nfunction helper() {}\n"], name: 'BundleOther');
    $path = bundleProbe(['sample/two' => ['classes' => ['Shared\\Thing']]], files: ['sample/two/src/f.php' => "<?php\n\nnamespace Shared;\n\nfunction helper() {}\n"]);

    expect(ModuleBundle::problemsFor($path))->toBe([
        'The bundle ships Shared\\Thing, which BundleOther also bundles.',
        'Shared\\helper() is also declared by BundleOther.',
    ]);
});

it('reports an unreadable bundle instead of failing', function () {
    $path = bundleProbe(['sample/greeter' => []]);
    File::put($path.'/bundle/bundle.json', '{not json');

    expect(ModuleBundle::problemsFor($path)[0])->toContain('bundle/bundle.json is not valid JSON');

    File::deleteDirectory($path.'/bundle');

    expect(ModuleBundle::problemsFor($path)[0])->toContain('run invoiceshelf-module bundle');
});
