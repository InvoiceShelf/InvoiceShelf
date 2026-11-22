<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Events\InvoiceBecameOverdue;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep that flags invoices nobody paid in time.
 *
 * A document qualifies when it is a plain invoice — a credit note is never
 * owed, so it stays out of the sweep whatever date it carries — is not already
 * flagged, has left the draft stage without reaching completion, and its due
 * date fell before today. The comparison is on the date alone, so an invoice
 * due today is still in good standing until tomorrow.
 */
class CheckInvoiceStatus extends Command
{
    protected $signature = 'check:invoices:status';

    protected $description = 'Check invoices status.';

    /**
     * Flag every invoice that has slipped past its due date.
     */
    public function handle(): void
    {
        $today = Carbon::now();

        $exempt = [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT];

        $overdue = Invoice::where('type', Invoice::TYPE_INVOICE)
            ->whereNotIn('status', $exempt)
            ->where('overdue', false)
            ->whereDate('due_date', '<', $today)
            ->get();

        foreach ($overdue as $invoice) {
            $invoice->overdue = true;
            printf("Invoice %s is OVERDUE \n", $invoice->invoice_number);
            $invoice->save();

            InvoiceBecameOverdue::dispatch((int) $invoice->id, (int) $invoice->company_id);
        }
    }
}
