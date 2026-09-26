<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Models\Company;
use Illuminate\Console\Command;

/**
 * Gives companies their copies of the role presets again: a copy that was
 * deleted by hand comes back, and a grant taken off one is restored. The
 * upgrade and every change to a preset do this already; this is the repair.
 */
class SyncRolePresets extends Command
{
    /** @var string */
    protected $signature = 'roles:sync-presets
        {--company= : Limit the run to one company, by id}';

    /** @var string */
    protected $description = 'Give every company its copies of the role presets, with their exact abilities';

    public function handle(RolePresetService $presets): int
    {
        $companyId = $this->option('company');

        if ($companyId !== null) {
            if (! Company::query()->whereKey($companyId)->exists()) {
                $this->error("No company with id {$companyId}.");

                return self::FAILURE;
            }

            $presets->syncCompany((int) $companyId);
            $this->line("Company {$companyId}: presets synced.");

            return self::SUCCESS;
        }

        $presets->syncAll();

        foreach ($presets->all() as $preset) {
            $this->line("{$preset->title}: synced in every company.");
        }

        return self::SUCCESS;
    }
}
