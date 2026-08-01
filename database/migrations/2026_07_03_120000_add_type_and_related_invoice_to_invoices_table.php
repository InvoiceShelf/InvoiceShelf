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
     *
     * The default on "type" is load-bearing, not cosmetic: it backfills every
     * pre-existing row with INVOICE. The serial-number queries are scoped by
     * type, so rows left with a NULL type would fall outside that scope and
     * numbering would restart from 1 on an existing install.
     *
     * related_invoice_id is a plain unsignedInteger with an index and NO
     * DB-level foreign key, which is the codebase-wide convention (see PRs
     * #618 / #683). invoices.id is INT UNSIGNED (the table uses increments(),
     * not bigIncrements()), so the column width has to match exactly, and
     * constraints across the three supported drivers have repeatedly broken
     * the v2 to v3 upgrade. The relation is declared on the Invoice model and
     * the delete-side bookkeeping lives in InvoiceService, not in the database.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'type')) {
                $table->string('type')->default('INVOICE')->after('status');
            }

            if (! Schema::hasColumn('invoices', 'related_invoice_id')) {
                $table->unsignedInteger('related_invoice_id')->nullable()->after('type')->index();
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
     *
     * The index on related_invoice_id has to go before the column does.
     * MySQL/MariaDB and PostgreSQL would drop it along with the column, but
     * SQLite does not: its "alter table drop column" leaves the index behind
     * and then fails with "no such column: related_invoice_id".
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'related_invoice_id')) {
                $table->dropIndex(['related_invoice_id']);
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
