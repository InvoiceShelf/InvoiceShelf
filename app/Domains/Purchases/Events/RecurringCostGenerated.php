<?php

namespace App\Domains\Purchases\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recurring schedule generated a bill or an expense.
 */
class RecurringCostGenerated
{
    use Dispatchable;

    /**
     * @param  string  $recordType  the record's morph alias, `bill` or `expense`
     */
    public function __construct(
        public readonly int $scheduleId,
        public readonly string $recordType,
        public readonly int $recordId,
        public readonly int $companyId,
    ) {}
}
