<?php

use App\Platform\Modules\Models\Module;
use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Sample\Local\Thing;
use Tests\Support\ModuleBundleFixture;

/*
 * `php artisan install:module` installs a module copied into Modules/ by hand,
 * as in local development; a bundled module must carry a bundle that fits.
 */

afterEach(function () {
    File::deleteDirectory(base_path('Modules/LocalBundled'));
    File::deleteDirectory(base_path('Modules/LocalFiles'));
});

it('refuses a bundled module that has not been bundled', function () {
    config(['invoiceshelf.marketplace.module_api_version' => '1.4.0']);
    ModuleBundleFixture::write(base_path('Modules/LocalBundled'), ['sample/local' => []]);
    File::deleteDirectory(base_path('Modules/LocalBundled/bundle'));

    expect(Artisan::call('install:module', ['module' => 'LocalBundled', 'version' => '1.0.0']))->toBe(1)
        ->and(Artisan::output())->toContain('run invoiceshelf-module bundle')
        ->and(Module::query()->where('name', 'LocalBundled')->exists())->toBeFalse();
});

it('installs a bundled module whose bundle fits', function () {
    config(['invoiceshelf.marketplace.module_api_version' => '1.4.0']);
    ModuleBundleFixture::write(base_path('Modules/LocalBundled'), ['sample/local' => ['classes' => ['Sample\\Local\\Thing']]]);
    ModuleRuntimeAutoloader::register('LocalBundled'); // bootstrap ran before the module was copied in

    expect(Artisan::call('install:module', ['module' => 'LocalBundled', 'version' => '1.0.0']))->toBe(0)
        ->and(Module::query()->where('name', 'LocalBundled')->value('enabled'))->toBeTrue()
        ->and(Thing::VERSION)->toBe('1.0.0');
});

it('runs a bundled module\'s files entries once the module is registered as enabled', function () {
    config(['invoiceshelf.marketplace.module_api_version' => '1.4.0']);
    ModuleBundleFixture::write(base_path('Modules/LocalFiles'), ['sample/local-helpers' => []], files: [
        'sample/local-helpers/src/functions.php' => "<?php\n\nnamespace Sample\\LocalHelpers;\n\nfunction ready(): bool\n{\n    return true;\n}\n",
    ]);
    ModuleRuntimeAutoloader::register('LocalFiles');

    expect(ModuleRuntimeAutoloader::hasLoadedFiles('LocalFiles'))->toBeFalse();

    Artisan::call('install:module', ['module' => 'LocalFiles', 'version' => '1.0.0']);

    expect(ModuleRuntimeAutoloader::hasLoadedFiles('LocalFiles'))->toBeTrue()
        ->and(Sample\LocalHelpers\ready())->toBeTrue();
});
