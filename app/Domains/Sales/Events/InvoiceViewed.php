<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer opened an invoice for the first time, from its emailed link.
 */
class InvoiceViewed
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
    ) {}
}
