<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Application\RecurringInvoiceService;
use Illuminate\Console\Command;

/**
 * Mint the invoices every active schedule has fallen due for.
 *
 * The due date on the row decides, not the minute the scheduler wakes up, so
 * a gap in scheduler uptime loses nothing. Each schedule is locked, generated
 * and moved on in one transaction (RecurrenceRunner), so two schedulers
 * running at once bill it once, and a failure leaves it due for a later run.
 */
class GenerateRecurringInvoices extends Command
{
    protected $signature = 'recurring-invoices:generate';

    protected $description = 'Generate invoices for every recurring invoice whose next run has fallen due.';

    public function handle(RecurringInvoiceService $service): int
    {
        $generated = $service->generateDue();

        $this->info($generated === 0
            ? 'No recurring invoice was generated.'
            : "Recurring invoices generated: {$generated}.");

        return self::SUCCESS;
    }
}
