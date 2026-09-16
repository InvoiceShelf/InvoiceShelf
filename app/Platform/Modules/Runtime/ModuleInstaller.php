<?php

namespace App\Platform\Modules\Runtime;

use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Events\ModuleInstalledEvent;
use App\Platform\Modules\Models\Module as ModelsModule;
use Illuminate\Support\Facades\Artisan;
use Nwidart\Modules\Facades\Module;

class ModuleInstaller
{
    /**
     * Migrate and activate a module already present on disk, write its registry
     * row, then announce the install and the activation.
     *
     * The second `Module::register()` is not a duplicate of the first: the
     * repository only registers modules the activator reports as enabled, so
     * before `module:enable` this one is skipped. Registering again once it is
     * on boots its providers -- the application is already booted by this point
     * -- which is what lets a listener on `ModuleEnabledEvent` see whatever the
     * module declared to the SDK registry.
     */
    public static function complete($module, $version): bool
    {
        Module::register();

        // `module:migrate` only runs paths the framework migrator already knows,
        // and a module's path is registered when its provider boots. A module that
        // is disabled on disk has not booted, so its migrations would be skipped
        // silently. Point the core migrate command at the directory instead.
        $migrations = Module::findOrFail($module)->getExtraPath('database/migrations');

        if (is_dir($migrations)) {
            Artisan::call('migrate', ['--path' => [$migrations], '--realpath' => true, '--force' => true]);
        }

        Artisan::call(sprintf('module:enable %s', $module));

        Module::register();

        $attributes = ['version' => $version, 'installed' => true, 'enabled' => true];
        $slug = Module::find($module)?->get('slug');

        if (is_string($slug) && $slug !== '') {
            $attributes['slug'] = $slug;
        }

        $record = ModelsModule::updateOrCreate(['name' => $module], $attributes);

        event(new ModuleInstalledEvent($record));
        event(new ModuleEnabledEvent($record));

        return true;
    }
}
