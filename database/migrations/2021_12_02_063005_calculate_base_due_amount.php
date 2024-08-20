<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
     */
    public function down(): void
    {
        //
    }
};
