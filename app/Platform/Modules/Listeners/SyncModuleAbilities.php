<?php

namespace App\Platform\Modules\Listeners;

use App\Platform\Modules\Application\ModuleAbilitySync;
use App\Platform\Modules\Events\ModuleEnabledEvent;
use App\Platform\Modules\Events\ModuleUninstalledEvent;

/**
 * Keeps Bouncer in step with the module lifecycle.
 *
 * Enabling a module hands its abilities to every company's owner role;
 * uninstalling one takes its ability rows away. Nothing listens for a disable:
 * a module switched off keeps its grants so switching it back on restores the
 * permissions its owners had arranged.
 */
class SyncModuleAbilities
{
    public function __construct(private readonly ModuleAbilitySync $sync) {}

    /**
     * Grant the module's abilities, booting its runtime first if need be.
     */
    public function handleEnabled(ModuleEnabledEvent $event): void
    {
        $module = $event->module;

        if (! is_object($module)) {
            return;
        }

        $name = (string) ($module->name ?? '');
        $slug = $this->sync->slugFor($module);

        if ($name === '' || $slug === '') {
            return;
        }

        $this->sync->grant($slug, $this->sync->entriesFor($name, $slug));
    }

    /**
     * Drop the module's abilities and every grant of them.
     */
    public function handleUninstalled(ModuleUninstalledEvent $event): void
    {
        $slug = $this->sync->slugFor($event->module);

        if ($slug === '') {
            return;
        }

        $this->sync->revoke($slug);
    }
}
