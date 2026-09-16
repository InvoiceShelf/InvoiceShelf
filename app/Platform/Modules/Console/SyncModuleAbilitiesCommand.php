<?php

namespace App\Platform\Modules\Console;

use App\Platform\Modules\Application\ModuleAbilitySync;
use App\Platform\Modules\Models\Module as ModelsModule;
use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;

/**
 * Reconciles module abilities with the registry rows.
 *
 * The lifecycle listener is the normal path; this is the repair tool for the
 * installs it never reached -- a module enabled before this feature existed, a
 * company created while the grant failed, or an uninstall that left rows
 * behind.
 */
class SyncModuleAbilitiesCommand extends Command
{
    /** @var string */
    protected $signature = 'modules:sync-abilities
        {module? : Limit the run to one installed module, by name}';

    /** @var string */
    protected $description = 'Grant enabled modules\' abilities to every company owner and clear removed ones';

    public function handle(ModuleAbilitySync $sync): int
    {
        $query = ModelsModule::query();

        if ($name = $this->argument('module')) {
            $query->where('name', $name);
        }

        $modules = $query->orderBy('name')->get();

        if ($modules->isEmpty()) {
            $this->error($name ? "No module registry row named {$name}." : 'No modules are registered.');

            return self::FAILURE;
        }

        foreach ($modules as $module) {
            $this->reconcile($sync, $module);
        }

        return self::SUCCESS;
    }

    /**
     * Settle one registry row.
     *
     * A row that is gone from disk or no longer installed loses its abilities.
     * An enabled one has them granted, its runtime registered first so the
     * module's provider has had the chance to declare them. A module that is
     * merely switched off is left exactly as it is.
     */
    private function reconcile(ModuleAbilitySync $sync, ModelsModule $module): void
    {
        $slug = $sync->slugFor($module);
        $runtime = Module::find($module->name);

        if (! $module->installed || $runtime === null) {
            $sync->revoke($slug);
            $this->line("{$module->name}: removed module abilities for '{$slug}'.");

            return;
        }

        if (! $module->enabled) {
            $this->line("{$module->name}: disabled, grants left in place.");

            return;
        }

        $entries = $sync->entriesFor($module->name, $slug);

        if ($entries === []) {
            $this->line("{$module->name}: declares no abilities.");

            return;
        }

        $sync->grant($slug, $entries);
        $this->line("{$module->name}: granted ".count($entries)." ability(ies) for '{$slug}'.");
    }
}
