<?php

namespace App\Adapters\Contacts;

use App\Domains\Contacts\Contracts\CustomerDataPurger;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

class EloquentCustomerDataPurger implements CustomerDataPurger
{
    public function purge(Customer $customer): void
    {
        $customer->estimates->each(function (Model $estimate): void {
            $this->clearDocumentData($estimate);
            $estimate->delete();
        });

        PaymentAllocation::query()
            ->whereIn('payment_id', $customer->payments()->select('id'))
            ->delete();
        $customer->payments->each->delete();

        $invoiceIds = $customer->invoices()->pluck('id');
        $customer->invoices->each(function (Invoice $invoice): void {
            $this->clearDocumentData($invoice);
            $invoice->transactions()->delete();
            $invoice->delete();
        });
        Invoice::query()->whereIn('related_invoice_id', $invoiceIds)->update(['related_invoice_id' => null]);

        $customer->expenses->each->delete();

        $customer->recurringInvoices->each(function (Model $recurringInvoice): void {
            $this->clearDocumentData($recurringInvoice);
            $recurringInvoice->occurrences()->delete();
            $recurringInvoice->delete();
        });
    }

    private function clearDocumentData(Model $document): void
    {
        $document->items->each(function (Model $item): void {
            $item->taxes()->delete();
            $item->delete();
        });

        $document->taxes()->delete();
    }
}
