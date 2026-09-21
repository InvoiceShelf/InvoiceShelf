<?php

use App\Domains\Sales\Models\RecurringInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Put every schedule's next run back in the future.
 *
 * `updateNextInvoiceDate()` used to recompute the date from `starts_at`, so
 * `next_invoice_at` was pinned to the first occurrence after the start date
 * and never moved again. Generation did not read the column, so the wrong
 * value only showed on screen. It does read it now, which makes those stale
 * dates dangerous: a schedule that started two years ago would look overdue
 * by every period since. Recomputing from today means the missed periods are
 * skipped rather than billed in a rush.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_invoices')) {
            return;
        }

        RecurringInvoice::query()
            ->whereIn('status', [RecurringInvoice::ACTIVE, RecurringInvoice::ON_HOLD])
            ->orderBy('id')
            ->chunkById(100, function ($batch): void {
                foreach ($batch as $recurringInvoice) {
                    try {
                        $recurringInvoice->updateNextInvoiceDate();
                    } catch (Throwable $exception) {
                        // A row whose cron expression no longer parses cannot be
                        // rescheduled here, and must not stop the migration. It
                        // stays as it was, and its schedule screen keeps showing
                        // the old date until someone edits the frequency.
                        continue;
                    }
                }
            });
    }

    /**
     * Irreversible by design: the previous values were wrong, and the dates
     * they would be restored to are derivable from `starts_at` anyway.
     */
    public function down(): void {}
};
