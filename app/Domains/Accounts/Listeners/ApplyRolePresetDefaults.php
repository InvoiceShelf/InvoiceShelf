<?php

namespace App\Domains\Accounts\Listeners;

use App\Domains\Accounts\Application\RolePresetService;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Schema;

/**
 * Offers the role presets their new catalogue defaults whenever `migrate`
 * finishes, with or without pending migrations.
 *
 * Every install path runs `migrate`: the web and headless installers, the
 * in-app updater, the Docker entrypoint on each start and a manual upgrade, so
 * an ability tagged for a preset in config/abilities.php reaches it on the
 * next of any of them without a migration of its own.
 */
class ApplyRolePresetDefaults
{
    public function __construct(private readonly RolePresetService $presets) {}

    public function handle(MigrationsEnded|NoPendingMigrations $event): void
    {
        if ($event->method !== 'up') {
            return;
        }

        if ($event instanceof MigrationsEnded && ($event->options['pretend'] ?? false)) {
            return;
        }

        if (! Schema::hasColumn('role_presets', 'applied_defaults')) {
            return;
        }

        $this->presets->applyDefaults();
    }
}
