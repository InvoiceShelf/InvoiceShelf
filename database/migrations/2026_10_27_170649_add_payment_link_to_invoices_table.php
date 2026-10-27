<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give an invoice a payment link of its own, overriding the company's
     * `invoice_payment_link` setting for that one invoice: a Payoneer or bank
     * payment request made for exactly this amount, say.
     *
     * Text rather than a string: the column holds a URL, and payment provider
     * URLs with a query string run long.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'payment_link')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('payment_link')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_link');
        });
    }
};
