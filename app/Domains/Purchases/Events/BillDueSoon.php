<?php

namespace App\Domains\Purchases\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An open bill falls due in a few days (BillDueSweep::DAYS_AHEAD).
 */
class BillDueSoon
{
    use Dispatchable;

    public function __construct(
        public readonly int $billId,
        public readonly int $companyId,
    ) {}
}
