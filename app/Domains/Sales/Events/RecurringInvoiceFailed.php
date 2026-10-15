<?php

namespace App\Domains\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recurring invoice's runs started failing, or started failing for a
 * different reason: it could not generate its invoice, or could not email
 * it. The reason is an error code.
 */
class RecurringInvoiceFailed
{
    use Dispatchable;

    public function __construct(
        public readonly int $scheduleId,
        public readonly int $companyId,
        public readonly string $reason,
    ) {}
}
