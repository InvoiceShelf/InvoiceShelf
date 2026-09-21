<?php

use App\Models\RecurringInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Put every schedule's next run back where it belongs.
 *
 * updateNextInvoiceDate() used to recompute the date from starts_at, so
 * next_invoice_at was pinned to the first occurrence after the schedule began
 * and never moved again. The column is what the schedule screen shows, so it
 * has been wrong on every schedule that has ever generated an invoice.
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
                        // rescheduled here and must not stop the migration.
                        continue;
                    }
                }
            });
    }

    /**
     * Irreversible by design: the previous values were wrong.
     */
    public function down(): void {}
};
