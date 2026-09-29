<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The daily sweep found an invoice past its due date and flagged it overdue.
 */
class InvoiceBecameOverdue
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $companyId,
    ) {}
}
