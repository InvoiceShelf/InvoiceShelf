<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invoice has nothing left to pay: its payments and credits now cover
 * the total.
 */
class InvoicePaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
    ) {}
}
