<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds credit-note support to the invoices table. A credit note
     * (Stornorechnung) is stored as an invoice row with type = CREDIT_NOTE
     * that references the original invoice it reverses. Because credit-note
     * line items carry negative unit prices, the invoice_items.price and
     * base_price columns are widened from UNSIGNED to signed.
     *
     * The sibling columns invoices.status / paid_status are plain strings, so
     * "type" follows the same convention (string + application-level
     * validation) rather than a DB enum.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'type')) {
                $table->string('type')->default('INVOICE')->after('status');
            }

            if (! Schema::hasColumn('invoices', 'related_invoice_id')) {
                // invoices.id is INT UNSIGNED on InvoiceShelf's schema (the table
                // uses increments(), not bigIncrements()), so the self-referencing
                // FK column must be unsignedInteger to match exactly. Using
                // unsignedBigInteger here breaks the FK on MySQL/MariaDB with
                // errno 150 "Foreign key constraint is incorrectly formed".
                $table->unsignedInteger('related_invoice_id')->nullable()->after('type');
                $table->foreign('related_invoice_id')
                    ->references('id')
                    ->on('invoices')
                    ->nullOnDelete();
            }
        });

        // Credit-note line items store negative unit prices, so these columns
        // must accept signed values. The other amount columns (total, tax,
        // discount_val, base_*) were already made signed in
        // 2024_10_09_103306_modify_invoices_to_allow_negative_values.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->bigInteger('price')->change();
            $table->bigInteger('base_price')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'related_invoice_id')) {
                $table->dropForeign(['related_invoice_id']);
                $table->dropColumn('related_invoice_id');
            }

            if (Schema::hasColumn('invoices', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->change();
            $table->unsignedBigInteger('base_price')->nullable()->change();
        });
    }
};
