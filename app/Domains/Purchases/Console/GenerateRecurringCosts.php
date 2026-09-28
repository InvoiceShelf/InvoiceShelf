<?php

namespace App\Domains\Purchases\Console;

use App\Domains\Purchases\Application\RecurringCostService;
use Illuminate\Console\Command;

/**
 * Generate the bills and expenses every active recurring schedule has fallen
 * due for, catching up runs missed while the scheduler was down.
 */
class GenerateRecurringCosts extends Command
{
    protected $signature = 'recurring-costs:generate';

    protected $description = 'Generate bills and expenses for every recurring schedule whose next run has fallen due.';

    public function handle(RecurringCostService $service): int
    {
        $generated = $service->generateDue();

        $this->info($generated === 0
            ? 'No recurring bill or expense is due.'
            : "Recurring bills and expenses generated: {$generated}.");

        return self::SUCCESS;
    }
}
