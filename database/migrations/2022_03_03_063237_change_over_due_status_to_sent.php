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
        $overdueInvoices = Invoice::where('status', 'OVERDUE')->get();

        if ($overdueInvoices) {
            $overdueInvoices->map(function ($overdueInvoice) {
                $overdueInvoice->status = Invoice::STATUS_SENT;
                $overdueInvoice->overdue = true;
                $overdueInvoice->save();
            });
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
