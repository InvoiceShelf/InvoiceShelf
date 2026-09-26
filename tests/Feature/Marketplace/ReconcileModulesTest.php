<?php

use App\Platform\Modules\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/*
 * `php artisan modules:reconcile`, which the Docker image runs on start
 * before migrations: a module that no longer fits this InvoiceShelf version
 * is disabled, so an upgrade never boots or migrates it.
 */

afterEach(function () {
    File::deleteDirectory(base_path('Modules/ReconcileFits'));
    File::deleteDirectory(base_path('Modules/ReconcileTooOld'));
});

function reconcileModule(string $name, array $compatibility): Module
{
    File::ensureDirectoryExists(base_path("Modules/{$name}"));
    File::put(base_path("Modules/{$name}/module.json"), json_encode(['name' => $name, 'compatibility' => $compatibility]));

    return Module::query()->create(['name' => $name, 'slug' => strtolower($name), 'version' => '1.0.0', 'installed' => true, 'enabled' => true, 'state' => 'installed']);
}

it('disables a module built for another InvoiceShelf version and keeps the others', function () {
    config(['app.version' => '3.0.0-alpha.10', 'invoiceshelf.marketplace.module_api_version' => '1.3.0']);
    $fits = reconcileModule('ReconcileFits', ['invoiceshelf' => '>=3.0.0-alpha.2 <4.0.0', 'module_api' => '^1.3.0', 'php' => '^8.4.0', 'extensions' => ['ext-json']]);
    $tooOld = reconcileModule('ReconcileTooOld', ['invoiceshelf' => '<3.0.0-alpha.5', 'module_api' => '^1.3.0']);

    expect(Artisan::call('modules:reconcile'))->toBe(0);

    expect($fits->fresh()->enabled)->toBeTrue()
        ->and($fits->fresh()->last_error)->toBeNull()
        ->and($tooOld->fresh()->enabled)->toBeFalse()
        ->and($tooOld->fresh()->installed)->toBeTrue()
        ->and($tooOld->fresh()->last_error)->toContain('Disabled after an upgrade')
        ->and($tooOld->fresh()->last_failed_at)->not->toBeNull();
});

it('disables a module that needs another module runtime API', function () {
    config(['invoiceshelf.marketplace.module_api_version' => '2.0.0']);
    $module = reconcileModule('ReconcileTooOld', ['module_api' => '^1.3.0']);

    Artisan::call('modules:reconcile');

    expect($module->fresh()->enabled)->toBeFalse();
});

it('leaves modules whose files are missing to the modules page', function () {
    $module = Module::query()->create(['name' => 'ReconcileGhost', 'slug' => 'ghost', 'version' => '1.0.0', 'installed' => true, 'enabled' => true, 'state' => 'installed']);

    expect(Artisan::call('modules:reconcile'))->toBe(0)
        ->and($module->fresh()->enabled)->toBeTrue();
});
