<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Application\RecurringInvoiceService;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Mint the invoices every active schedule has fallen due for.
 *
 * The schedule used to be read the other way round: the scheduler registered
 * one cron entry per recurring invoice at boot, so a run only happened when
 * the expression matched the very minute the scheduler woke up. That made
 * generation depend on how often the scheduler ran rather than on what was
 * owed, which meant a second run in the same minute billed twice, and a gap in
 * scheduler uptime lost the period altogether.
 *
 * Here the due date on the row decides. Each row is claimed before it is
 * billed, by moving `next_invoice_at` on with the old value in the where
 * clause, so exactly one caller can win it even when two schedulers run at
 * once. Everything after the claim is therefore safe to repeat.
 */
class GenerateRecurringInvoices extends Command
{
    protected $signature = 'recurring-invoices:generate';

    protected $description = 'Generate invoices for every recurring invoice whose next run has fallen due.';

    public function handle(RecurringInvoiceService $service): int
    {
        $due = 0;

        RecurringInvoice::query()
            ->where('status', RecurringInvoice::ACTIVE)
            ->whereNotNull('next_invoice_at')
            ->where('next_invoice_at', '<=', Carbon::now()->format('Y-m-d H:i:s'))
            ->orderBy('id')
            ->chunkById(100, function ($batch) use ($service, &$due): void {
                foreach ($batch as $recurringInvoice) {
                    if (! $this->claim($recurringInvoice)) {
                        continue;
                    }

                    $due++;
                    $service->generateInvoice($recurringInvoice, advanceSchedule: false);

                    $this->info(sprintf(
                        'Recurring invoice %d billed, next run %s.',
                        $recurringInvoice->id,
                        $recurringInvoice->next_invoice_at,
                    ));
                }
            });

        if ($due === 0) {
            $this->info('No recurring invoice is due.');
        }

        return self::SUCCESS;
    }

    /**
     * Take ownership of a due schedule by advancing it.
     *
     * The update is conditional on the date still being the one we read, so a
     * second process working the same row writes nothing and is told to leave
     * it alone. The in-memory model is advanced to match, which is what the
     * service then skips doing.
     */
    private function claim(RecurringInvoice $recurringInvoice): bool
    {
        $claimed = $recurringInvoice->next_invoice_at;

        $next = RecurringInvoice::getNextInvoiceDate(
            $recurringInvoice->frequency,
            $recurringInvoice->nextRunCountsFrom(),
            $recurringInvoice->companyTimeZone(),
        );

        $won = RecurringInvoice::query()
            ->whereKey($recurringInvoice->id)
            ->where('next_invoice_at', $claimed)
            ->update(['next_invoice_at' => $next]) === 1;

        if ($won) {
            $recurringInvoice->next_invoice_at = $next;
        }

        return $won;
    }
}
