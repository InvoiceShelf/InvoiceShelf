<?php

use Cron\CronExpression;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $applicationTimezone = config('app.timezone', 'UTC');
        $cutover = Carbon::now($applicationTimezone);

        DB::table('recurring_invoices')
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->chunkById(500, function ($recurringInvoices) use ($applicationTimezone, $cutover): void {
                $timezones = DB::table('company_settings')
                    ->whereIn('company_id', $recurringInvoices->pluck('company_id')->unique())
                    ->where('option', 'time_zone')
                    ->pluck('value', 'company_id');

                foreach ($recurringInvoices as $recurringInvoice) {
                    $timezone = $timezones->get($recurringInvoice->company_id) ?: $applicationTimezone;

                    try {
                        new DateTimeZone($timezone);
                    } catch (Exception) {
                        $timezone = $applicationTimezone;
                    }

                    try {
                        $cron = new CronExpression($recurringInvoice->frequency);
                        $startsAt = Carbon::parse($recurringInvoice->starts_at, $timezone);
                        $reference = $startsAt->greaterThan($cutover->copy()->setTimezone($timezone))
                            ? $startsAt
                            : $cutover->copy()->setTimezone($timezone);
                        $next = $reference->greaterThan($cutover->copy()->setTimezone($timezone))
                            && $reference->second === 0
                            && $cron->isDue($reference, $timezone)
                            ? $reference
                            : Carbon::instance($cron->getNextRunDate($reference, 0, false, $timezone));
                    } catch (Throwable $exception) {
                        DB::table('recurring_invoices')
                            ->where('id', $recurringInvoice->id)
                            ->update([
                                'status' => 'ON_HOLD',
                            ]);

                        Log::warning('Put recurring invoice on hold during scheduler cutover because its schedule is invalid.', [
                            'recurring_invoice_id' => $recurringInvoice->id,
                            'exception' => $exception,
                        ]);

                        continue;
                    }

                    // Keep database write failures outside the cron/date error
                    // boundary. A systemic migration failure must abort instead
                    // of silently putting otherwise valid templates on hold.
                    DB::table('recurring_invoices')
                        ->where('id', $recurringInvoice->id)
                        ->update([
                            'next_invoice_at' => $next->setTimezone($applicationTimezone)->format('Y-m-d H:i:s'),
                        ]);
                }
            });

        // Add the index after the data pass. On databases whose DDL is not
        // transactional, a failed normalization can then be rerun safely.
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->index(['status', 'next_invoice_at'], 'recurring_invoices_status_next_invoice_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropIndex('recurring_invoices_status_next_invoice_at_index');
        });
    }
};
