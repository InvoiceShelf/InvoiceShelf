<?php

namespace App\Domains\Purchases\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recurring schedule's runs started failing, or started failing for a
 * different reason. The reason is an error code.
 */
class RecurringCostFailed
{
    use Dispatchable;

    public function __construct(
        public readonly int $scheduleId,
        public readonly int $companyId,
        public readonly string $reason,
    ) {}
}
