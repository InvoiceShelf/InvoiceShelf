<?php

namespace App\Domains\Purchases\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An open bill with something left to pay is past its due date.
 */
class BillBecameOverdue
{
    use Dispatchable;

    public function __construct(
        public readonly int $billId,
        public readonly int $companyId,
    ) {}
}
