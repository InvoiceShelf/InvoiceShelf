<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\RecurringCost;

class RecurringCostPolicy extends PurchasePolicy
{
    protected const MODEL = RecurringCost::class;

    protected const ABILITY = 'recurring-cost';
}
