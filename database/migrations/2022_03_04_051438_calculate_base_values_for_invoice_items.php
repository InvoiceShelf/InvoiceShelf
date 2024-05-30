<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\InvoiceItem;
use App\Models\Tax;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $taxes = Tax::whereRelation('invoiceItem', 'base_amount', null)->get();

        if ($taxes) {
            $taxes->map(function ($tax) {
                $invoiceItem = InvoiceItem::find($tax->invoice_item_id);
                $exchange_rate = $invoiceItem->exchange_rate;
                $tax->exchange_rate = $exchange_rate;
                $tax->base_amount = $tax->amount * $exchange_rate;
                $tax->save();
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
