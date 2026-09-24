<?php

namespace App\Domains\Sales\Application;

use App\Domains\Sales\Models\Invoice;
use App\Support\MoneyConversion;

class InvoiceBalanceService
{
    public function allocatedTotal(Invoice $invoice): int
    {
        return (int) $invoice->allocations()->sum('amount');
    }

    public function creditedTotal(Invoice $invoice): int
    {
        return -(int) $invoice->creditNotes()->sum('total');
    }

    /**
     * Recalculate an invoice exclusively from durable credits and payment
     * allocations. Callers that change allocations must hold the invoice lock.
     */
    public function recalculate(Invoice $invoice): void
    {
        $allocated = $this->allocatedTotal($invoice);
        $credited = $this->creditedTotal($invoice);
        $due = max(0, (int) $invoice->total - $allocated - $credited);

        $invoice->due_amount = $due;
        $invoice->base_due_amount = MoneyConversion::toBaseMinor($due, $invoice->exchange_rate);

        if ($due === 0) {
            // Nothing left outstanding, so the document closes out on both
            // axes at once.
            $invoice->forceFill([
                'status' => Invoice::STATUS_COMPLETED,
                'paid_status' => Invoice::STATUS_PAID,
            ]);
            $invoice->overdue = false;
        } else {
            $invoice->status = $invoice->getPreviousStatus();
            $invoice->paid_status = $allocated > 0
                ? Invoice::STATUS_PARTIALLY_PAID
                : Invoice::STATUS_UNPAID;
        }

        $invoice->save();
    }
}
