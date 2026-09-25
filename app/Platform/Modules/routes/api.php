<?php

use App\Platform\Modules\Http\Controllers\Admin\MarketplacePairingController;
use App\Platform\Modules\Http\Controllers\Admin\ModuleInstallationController;
use App\Platform\Modules\Http\Controllers\Admin\ModulesController;
use App\Platform\Modules\Http\Controllers\Company\CompanyModulesController;
use App\Platform\Modules\Http\Controllers\Company\ModuleSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'company'])
    ->prefix('api/v1')
    ->group(function () {
        Route::prefix('modules')->group(function () {
            Route::get('/', [ModulesController::class, 'index']);
            Route::get('/pairing', [MarketplacePairingController::class, 'status']);

            // On a managed install the provider installs and pairs modules.
            Route::middleware('not-managed')->group(function () {
                Route::post('/pairing/start', [MarketplacePairingController::class, 'start']);
                Route::post('/pairing/poll', [MarketplacePairingController::class, 'poll']);
                Route::delete('/pairing', [MarketplacePairingController::class, 'disconnect']);
                Route::post('/{module}/uninstall', [ModuleInstallationController::class, 'uninstall']);
                Route::post('/install', [ModuleInstallationController::class, 'install']);
            });

            Route::get('/{module}', [ModulesController::class, 'show']);
            Route::post('/{module}/enable', [ModulesController::class, 'enable']);
            Route::post('/{module}/disable', [ModulesController::class, 'disable']);
            Route::get('/{slug}/settings', [ModuleSettingsController::class, 'show']);
            Route::put('/{slug}/settings', [ModuleSettingsController::class, 'update']);
        });

        Route::get('/company-modules', [CompanyModulesController::class, 'index']);
    });
