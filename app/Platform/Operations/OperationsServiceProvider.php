<?php

namespace App\Platform\Operations;

use App\Platform\Operations\Application\RuntimeConfigurationService;
use App\Platform\Operations\Console\ResetApp;
use App\Platform\Operations\Console\RetireShippedKey;
use App\Platform\Operations\Console\UpdateCommand;
use App\Platform\Operations\Installation\Authentication\InstallWizardAuth;
use App\Platform\Operations\Policies\OperationsAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuntimeConfigurationService::class);
    }

    public function boot(RuntimeConfigurationService $runtimeConfiguration): void
    {
        $this->configureInstallWizardTokenAuth();

        Gate::define('manage settings', [OperationsAccessPolicy::class, 'manage']);
        Gate::define('manage update app', [OperationsAccessPolicy::class, 'manage']);

        $this->commands([
            ResetApp::class,
            RetireShippedKey::class,
            UpdateCommand::class,
        ]);

        $runtimeConfiguration->apply();
    }

    private function configureInstallWizardTokenAuth(): void
    {
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $request = request();

            if (! $request instanceof Request || ! $request->attributes->get('install_wizard', false)) {
                return $isValid;
            }

            return $accessToken->can(InstallWizardAuth::TOKEN_ABILITY);
        });
    }
}
