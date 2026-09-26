<?php

namespace App\Platform\Modules;

use App\Platform\Modules\Console\InstallModuleCommand;
use App\Platform\Modules\Console\ReconcileModulesCommand;
use App\Platform\Modules\Console\SyncModuleAbilitiesCommand;
use App\Platform\Modules\Console\UninstallModuleCommand;
use App\Platform\Modules\Contracts\ModuleSettingsStore;
use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Events\ModuleUninstalledEvent;
use App\Platform\Modules\Infrastructure\BouncerModuleAuthorization;
use App\Platform\Modules\Infrastructure\EloquentCompanyDataReader;
use App\Platform\Modules\Infrastructure\EloquentHostSettingsStore;
use App\Platform\Modules\Infrastructure\EloquentModuleSettingsStore;
use App\Platform\Modules\Listeners\SyncModuleAbilities;
use App\Platform\Modules\Policies\ModulePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Contracts\Host\CompanyDataReader;
use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;
use InvoiceShelf\Modules\Contracts\Host\SettingsStore;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ModuleSettingsStore::class, EloquentModuleSettingsStore::class);
        $this->app->bind(SettingsStore::class, EloquentHostSettingsStore::class);
        $this->app->bind(ModuleAuthorization::class, BouncerModuleAuthorization::class);
        $this->app->bind(CompanyDataReader::class, EloquentCompanyDataReader::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallModuleCommand::class,
                ReconcileModulesCommand::class,
                SyncModuleAbilitiesCommand::class,
                UninstallModuleCommand::class,
            ]);
        }

        // A module's abilities are Bouncer rows, so they have to be written
        // when the module is switched on and cleared when it is removed.
        Event::listen(ModuleEnabledEvent::class, [SyncModuleAbilities::class, 'handleEnabled']);
        Event::listen(ModuleUninstalledEvent::class, [SyncModuleAbilities::class, 'handleUninstalled']);

        Gate::define('manage modules', [ModulePolicy::class, 'manageModules']);
        Gate::define('manage module settings', [ModulePolicy::class, 'manageSettings']);

        $this->app->booted(fn () => $this->dropMissingModuleViewPaths());
    }

    /**
     * Forget the view directories modules register but do not ship.
     *
     * A module's service provider registers `resources/views` whether or not
     * the module has any, and `view:cache` fails on a directory that does not
     * exist. The Docker image runs `optimize` on every start, so one installed
     * module without views stopped the container from starting.
     */
    public function dropMissingModuleViewPaths(): void
    {
        $modules = rtrim((string) config('modules.paths.modules'), '/').'/';
        $finder = View::getFinder();

        foreach ($finder->getHints() as $namespace => $paths) {
            $kept = array_values(array_filter(
                $paths,
                fn (string $path): bool => ! str_starts_with($path, $modules) || is_dir($path),
            ));

            if ($kept !== array_values($paths)) {
                $finder->replaceNamespace($namespace, $kept);
            }
        }
    }
}
