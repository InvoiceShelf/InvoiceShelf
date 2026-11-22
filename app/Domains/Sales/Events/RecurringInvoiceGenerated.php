<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recurring invoice generated an invoice, and it has been sent to the
 * customer if the schedule sends automatically.
 */
class RecurringInvoiceGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly int $scheduleId,
        public readonly int $invoiceId,
        public readonly int $companyId,
    ) {}
}
