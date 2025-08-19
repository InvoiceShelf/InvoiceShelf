<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckInvoiceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:invoices:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check invoices status.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $date = Carbon::now();
        $status = [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT];
        $invoices = Invoice::whereNotIn('status', $status)
            ->where('overdue', false)
            ->whereDate('due_date', '<', $date)
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->overdue = true;
            printf("Invoice %s is OVERDUE \n", $invoice->invoice_number);
            $invoice->save();
        }
    }
}
