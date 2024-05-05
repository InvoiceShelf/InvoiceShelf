<?php

use Illuminate\Database\Migrations\Migration;
use InvoiceShelf\Models\Invoice;

class CalculateBaseDueAmount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $invoices = Invoice::all();

        foreach ($invoices as $invoice) {
            if ($invoice->exchange_rate) {
                $invoice->base_due_amount = $invoice->due_amount * $invoice->exchange_rate;
                $invoice->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
