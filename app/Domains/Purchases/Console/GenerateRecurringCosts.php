<?php

namespace App\Domains\Purchases\Console;

use App\Domains\Purchases\Application\RecurringCostService;
use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Console\Command;

class GenerateRecurringCosts extends Command
{
    protected $signature = 'recurring-costs:generate';

    protected $description = 'Generate due bills and explicitly enabled recurring paid expenses.';

    public function handle(RecurringCostService $service): int
    {
        $count = 0;
        RecurringCost::query()->where('status', 'ACTIVE')->whereNotNull('next_run_at')->chunkById(100, function ($rows) use ($service, &$count): void {
            foreach ($rows as $row) {
                $count += $service->generate($row);
            }
        });
        $this->info("Generated {$count} recurring cost occurrences.");

        return self::SUCCESS;
    }
}
