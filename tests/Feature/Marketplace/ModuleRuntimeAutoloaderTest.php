<?php

use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use Illuminate\Support\Facades\File;
use Modules\AutoloadProbe\Providers\AutoloadProbeServiceProvider;
use Sample\BundledOnly\Widget;
use Sample\Refresh\Upgraded;
use Sample\Refresh\Warmup;
use Tests\Support\ModuleBundleFixture;

afterEach(function () {
    File::deleteDirectory(base_path('Modules/AutoloadProbe'));
});

it('autoloads an installed module before Laravel package providers are registered', function () {
    $modulePath = base_path('Modules/AutoloadProbe');
    File::ensureDirectoryExists($modulePath.'/app/Providers');
    File::put($modulePath.'/module.json', json_encode(['name' => 'AutoloadProbe'], JSON_THROW_ON_ERROR));
    File::put($modulePath.'/app/Providers/AutoloadProbeServiceProvider.php', <<<'PHP'
<?php

namespace Modules\AutoloadProbe\Providers;

class AutoloadProbeServiceProvider {}
PHP);

    ModuleRuntimeAutoloader::registerInstalledModules(base_path('Modules'));

    expect(class_exists(AutoloadProbeServiceProvider::class))->toBeTrue();
});

it('loads bundled classes after the host, so a class the host ships keeps the host copy', function () {
    $path = base_path('Modules/AutoloadProbe');
    ModuleBundleFixture::write($path, [
        'sample/bundled-only' => ['classes' => ['Sample\\BundledOnly\\Widget']],
        'sample/shadow' => ['classes' => ['Hashids\\Hashids']],
    ]);

    ModuleRuntimeAutoloader::registerInstalledModules(base_path('Modules'));

    expect((new ReflectionClass(Widget::class))->getFileName())->toBe($path.'/bundle/sample/bundled-only/src/Sample/BundledOnly/Widget.php')
        ->and((new ReflectionClass(Hashids\Hashids::class))->getFileName())->toContain(base_path('vendor/hashids/hashids'));
});

it('reads a replaced classmap after a refresh instead of the cached one', function () {
    $path = base_path('Modules/AutoloadProbe');
    ModuleBundleFixture::write($path, ['sample/first' => ['version' => '1.0.0', 'classes' => ['Sample\\Refresh\\Warmup', 'Sample\\Refresh\\Upgraded']]]);
    ModuleRuntimeAutoloader::register('AutoloadProbe', base_path('Modules'));
    class_exists(Warmup::class); // caches the 1.0.0 classmap

    File::deleteDirectory($path);
    ModuleBundleFixture::write($path, ['sample/second' => ['version' => '2.0.0', 'classes' => ['Sample\\Refresh\\Upgraded']]]);
    ModuleRuntimeAutoloader::refresh('AutoloadProbe', base_path('Modules'));

    expect(Upgraded::VERSION)->toBe('2.0.0');
});

it('loads a bundled module\'s files entries once', function () {
    $path = base_path('Modules/AutoloadProbe');
    ModuleBundleFixture::write($path, ['sample/helpers' => []], files: [
        'sample/helpers/src/functions.php' => "<?php\n\nnamespace Sample\\AutoloadHelpers;\n\nfunction loaded(): string\n{\n    return 'yes';\n}\n",
    ]);

    expect(ModuleRuntimeAutoloader::hasLoadedFiles('AutoloadProbe'))->toBeFalse();

    ModuleRuntimeAutoloader::loadBundleFiles('AutoloadProbe', base_path('Modules'));
    ModuleRuntimeAutoloader::loadBundleFiles('AutoloadProbe', base_path('Modules'));

    expect(Sample\AutoloadHelpers\loaded())->toBe('yes')
        ->and(ModuleRuntimeAutoloader::hasLoadedFiles('AutoloadProbe'))->toBeTrue();
});
