<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment reminder went out to a customer, on its schedule (offsetDays,
 * days from the due date) or sent by hand (no offset).
 */
class InvoiceReminderSent
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
        public readonly ?int $offsetDays,
    ) {}
}
