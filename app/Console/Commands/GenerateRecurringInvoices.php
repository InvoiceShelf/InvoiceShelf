<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Bill every active recurring invoice whose next run has come.
 *
 * Replaces the one cron entry per schedule that routes/console.php used to
 * register. Those only billed when the expression matched the exact minute
 * the scheduler woke up, in the company's time zone, so a crontab that ran
 * late, less often than every minute, or not at all for a while silently
 * skipped the period.
 */
class GenerateRecurringInvoices extends Command
{
    /**
     * How long a schedule whose generation failed is left alone, in seconds,
     * so a broken schedule is retried hourly instead of filling the log every
     * minute.
     */
    private const RETRY_AFTER_SECONDS = 3600;

    protected $signature = 'recurring-invoices:generate';

    protected $description = 'Generate invoices for every recurring invoice whose next run has come.';

    public function handle(): int
    {
        $generated = 0;

        RecurringInvoice::query()
            ->where('status', RecurringInvoice::ACTIVE)
            ->whereNotNull('next_invoice_at')
            ->where('next_invoice_at', '<=', Carbon::now()->format('Y-m-d H:i:s'))
            ->chunkById(100, function ($schedules) use (&$generated): void {
                foreach ($schedules as $schedule) {
                    $failedKey = 'recurring-invoice-failed:'.$schedule->id;

                    if (Cache::has($failedKey)) {
                        continue;
                    }

                    try {
                        if ($schedule->generateDueInvoice()) {
                            $generated++;
                        }
                    } catch (Throwable $exception) {
                        Cache::put($failedKey, true, self::RETRY_AFTER_SECONDS);

                        report($exception);
                    }
                }
            });

        $this->info($generated === 0
            ? 'No recurring invoice was generated.'
            : "Recurring invoices generated: {$generated}.");

        return self::SUCCESS;
    }
}
