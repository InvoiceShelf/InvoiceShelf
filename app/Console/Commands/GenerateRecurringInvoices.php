<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:recurring-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices from active recurring invoice templates.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $now = Carbon::now();

        // Fetch all active recurring invoices where next_invoice_at <= now
        $recurringInvoices = RecurringInvoice::where('status', RecurringInvoice::ACTIVE)
            ->whereNotNull('next_invoice_at')
            ->where('next_invoice_at', '<=', $now)
            ->get();

        if ($recurringInvoices->isEmpty()) {
            $this->info('No recurring invoices to generate at this time.');

            return;
        }

        foreach ($recurringInvoices as $recurringInvoice) {
            try {
                $recurringInvoice->generateInvoice();
                $this->info("Generated invoice from recurring invoice ID: {$recurringInvoice->id}");
            } catch (\Exception $e) {
                $this->error("Failed to generate invoice for recurring invoice ID: {$recurringInvoice->id}. Error: {$e->getMessage()}");
            }
        }

        $this->info('Completed generating recurring invoices.');
    }
}
