<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Application\RolePresetService;
use Illuminate\Console\Command;

/**
 * Offers the role presets the abilities config/abilities.php lists them for
 * and they have not been offered before. `migrate` does this already; this is
 * the manual run.
 */
class ApplyRolePresetDefaults extends Command
{
    /** @var string */
    protected $signature = 'roles:apply-preset-defaults';

    /** @var string */
    protected $description = 'Give the role presets the default abilities they have not been offered yet';

    public function handle(RolePresetService $presets): int
    {
        $offered = $presets->applyDefaults();

        if ($offered === []) {
            $this->line('Every preset already has its defaults.');

            return self::SUCCESS;
        }

        foreach ($offered as $key => $abilities) {
            $this->line("{$key}: ".implode(', ', $abilities));
        }

        return self::SUCCESS;
    }
}
