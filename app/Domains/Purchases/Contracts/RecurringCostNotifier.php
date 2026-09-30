<?php

namespace App\Domains\Purchases\Contracts;

use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Database\Eloquent\Model;

/**
 * Tells a recurring schedule's creator what a run did.
 */
interface RecurringCostNotifier
{
    /**
     * A run generated this bill or expense.
     */
    public function generated(RecurringCost $schedule, Model $record): void;

    /**
     * A run failed, for the given reason (an error code); the schedule will
     * try again later.
     */
    public function failed(RecurringCost $schedule, string $reason): void;
}
