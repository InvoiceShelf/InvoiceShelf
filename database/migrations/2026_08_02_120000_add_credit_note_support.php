<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds credit-note support across invoices, invoice_items, taxes and
     * company_settings. A credit note (Stornorechnung) is stored as an invoice
     * row with type = CREDIT_NOTE that references the original invoice it
     * reverses, either in full or in part.
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
     * related_invoice_id and source_invoice_item_id are plain unsignedInteger
     * columns with an index and NO DB-level foreign key, which is the
     * codebase-wide convention (see PRs #618 / #683). invoices.id and
     * invoice_items.id are INT UNSIGNED (both tables use increments(), not
     * bigIncrements()), so the column widths have to match exactly, and
     * constraints across the three supported drivers have repeatedly broken
     * the v2 to v3 upgrade. The relations are declared on the Invoice and
     * InvoiceItem models and the delete-side bookkeeping lives in
     * InvoiceService, not in the database.
     *
     * source_invoice_item_id records which line of the original invoice a
     * credit-note line credits, which is what partial credit notes need in
     * order to know how much of each line has already been credited.
     *
     * Credit-note line items store negative unit prices, so invoice_items.price
     * and base_price are widened from UNSIGNED to signed. The other amount
     * columns (total, tax, discount_val, base_*) were already made signed in
     * 2024_10_09_103306_modify_invoices_to_allow_negative_values.
     *
     * taxes.base_amount gets the same treatment, which fixes a pre-existing
     * bug rather than only serving credit notes: taxes.amount was widened to
     * signed in 2024_02_08_181804_taxes_amount_as_signed, but base_amount
     * (added as unsignedBigInteger in 2021_07_16_075100) was left behind. Any
     * negative tax row on a company with an exchange rate therefore fails to
     * insert under strict-mode MySQL, and credit notes produce exactly those
     * rows.
     *
     * Finally, every existing company is given a credit_note_number_format.
     * New companies get the setting from CompanyService::setupDefaultSettings(),
     * but installs created before independent credit note numbering have no
     * such row. CompanySetting::getSetting() then returns null, the format
     * string is empty, and SerialNumberService silently generates an empty
     * document number, so the data has to be backfilled rather than defaulted
     * in code.
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

            if (! Schema::hasColumn('invoices', 'credit_reason')) {
                $table->text('credit_reason')->nullable()->after('related_invoice_id');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->bigInteger('price')->change();
            $table->bigInteger('base_price')->nullable()->change();

            if (! Schema::hasColumn('invoice_items', 'source_invoice_item_id')) {
                $table->unsignedInteger('source_invoice_item_id')->nullable()->after('invoice_id')->index();
            }
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->bigInteger('base_amount')->nullable()->change();
        });

        Company::all()->each(function ($company) {
            $format = CompanySetting::getSetting('credit_note_number_format', $company->id);

            if ($format) {
                return;
            }

            CompanySetting::setSettings([
                'credit_note_number_format' => '{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            ], $company->id);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The indexes on related_invoice_id and source_invoice_item_id have to go
     * before their columns do. MySQL/MariaDB and PostgreSQL would drop them
     * along with the columns, but SQLite does not: its "alter table drop
     * column" leaves the index behind and then fails with "no such column".
     */
    public function down(): void
    {
        CompanySetting::where('option', 'credit_note_number_format')->delete();

        Schema::table('taxes', function (Blueprint $table) {
            $table->unsignedBigInteger('base_amount')->nullable()->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'source_invoice_item_id')) {
                $table->dropIndex(['source_invoice_item_id']);
                $table->dropColumn('source_invoice_item_id');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->change();
            $table->unsignedBigInteger('base_price')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'credit_reason')) {
                $table->dropColumn('credit_reason');
            }

            if (Schema::hasColumn('invoices', 'related_invoice_id')) {
                $table->dropIndex(['related_invoice_id']);
                $table->dropColumn('related_invoice_id');
            }

            if (Schema::hasColumn('invoices', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
