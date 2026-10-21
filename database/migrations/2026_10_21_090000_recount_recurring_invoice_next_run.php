<?php

use App\Models\RecurringInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Recount every schedule's next run from now, before the scheduler starts
 * billing by it.
 *
 * recurring-invoices:generate bills any schedule whose next run is in the
 * past. Saving a schedule used to count that date from its start date, so an
 * edited schedule that had been running for a while carries a date long past.
 * Without this, upgrading would bill those schedules again at once.
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
