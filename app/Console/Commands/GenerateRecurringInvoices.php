<?php

namespace App\Console\Commands;

use App\Services\Document\RecurringInvoiceService;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'generate:recurring-invoices';

    protected $description = 'Generate due recurring invoices.';

    /**
     * Execute the console command.
     */
    public function handle(RecurringInvoiceService $recurringInvoiceService): int
    {
        $generated = $recurringInvoiceService->generateDueInvoices();

        $this->info("Generated {$generated} recurring invoice(s).");

        return self::SUCCESS;
    }
}
