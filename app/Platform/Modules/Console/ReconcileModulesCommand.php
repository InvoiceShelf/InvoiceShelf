<?php

namespace App\Platform\Modules\Console;

use App\Platform\Modules\Models\Module as InstalledModule;
use App\Platform\Modules\Runtime\DatabaseActivator;
use App\Platform\Modules\Runtime\ModuleCompatibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Disables every enabled module whose module.json no longer fits this host,
 * so a core upgrade never boots or migrates a module built for another
 * version. The Docker image runs it on start, before migrations; it is safe
 * to run any time. A disabled module keeps its data and shows why, and the
 * owner can install a compatible release.
 */
class ReconcileModulesCommand extends Command
{
    protected $signature = 'modules:reconcile';

    protected $description = 'Disable installed modules that no longer fit this InvoiceShelf version';

    public function handle(DatabaseActivator $activator): int
    {
        if (! Schema::hasTable('modules')) {
            return self::SUCCESS;
        }

        $disabled = 0;
        $modules = InstalledModule::query()->where('installed', true)->where('enabled', true)->orderBy('name')->get();
        foreach ($modules as $module) {
            $manifest = base_path('Modules/'.$module->name.'/module.json');
            if (! File::exists($manifest)) {
                continue; // missing runtime files are reported by the modules page
            }

            $problems = ModuleCompatibility::problems(json_decode((string) File::get($manifest), true)['compatibility'] ?? null);
            if ($problems === []) {
                continue;
            }

            $activator->setActiveByName($module->name, false);
            $module->forceFill([
                'last_error' => 'Disabled after an upgrade: '.implode(' ', $problems),
                'last_failed_at' => now(),
            ])->save();
            Log::warning('Module disabled: it does not fit this InvoiceShelf version.', ['module' => $module->name, 'problems' => $problems]);
            $this->components->warn("{$module->name} disabled: ".implode(' ', $problems));
            $disabled++;
        }

        if ($disabled === 0) {
            $this->components->info('Every enabled module fits this InvoiceShelf version.');
        }

        return self::SUCCESS;
    }
}
