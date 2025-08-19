<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MigrationCheckServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if (! $this->areMigrationsUpToDate()) {
            $this->showMigrationWarningPage();
        }
    }

    /**
     * Check if migrations are up to date.
     *
     * @return bool
     */
    protected function areMigrationsUpToDate()
    {
        $migrated = DB::table('migrations')->count();
        $totalMigrations = count(\File::files(database_path('migrations')));

        return $migrated === $totalMigrations;
    }

    /**
     * Show a custom migration warning page.
     *
     * @return void
     */
    protected function showMigrationWarningPage()
    {
        $view = View::make('migrations.warning');

        echo $view->render();
        exit;
    }
}
