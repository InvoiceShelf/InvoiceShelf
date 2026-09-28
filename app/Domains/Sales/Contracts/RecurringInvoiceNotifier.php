<?php

namespace App\Domains\Sales\Contracts;

use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;

/**
 * Tells a recurring invoice's creator what a run did.
 */
interface RecurringInvoiceNotifier
{
    /**
     * A run generated this invoice.
     */
    public function generated(RecurringInvoice $schedule, Invoice $invoice): void;

    /**
     * A run failed, or its invoice could not be emailed, for the given
     * reason (an error code).
     */
    public function failed(RecurringInvoice $schedule, string $reason): void;
}
