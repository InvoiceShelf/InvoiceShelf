<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the EN 16931 codes structured e-invoices are written with: the tax
     * category code (BT-151) and exemption reason (BT-121) of a tax type, and
     * the UN/ECE Recommendation 20 unit code (BT-130) of a unit.
     *
     * The codes are NOT NULL with a default, `S` (standard rate) and `C62`
     * (piece), so existing rows are backfilled as the columns are created.
     * The defaults are literals rather than model constants: a migration must
     * keep describing the schema it created even if a model's default moves.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tax_types', 'tax_category_code')) {
            Schema::table('tax_types', function (Blueprint $table) {
                $table->string('tax_category_code', 4)->default('S')->after('percent');
                $table->string('tax_exemption_reason', 255)->nullable()->after('tax_category_code');
            });
        }

        if (! Schema::hasColumn('units', 'unit_code')) {
            Schema::table('units', function (Blueprint $table) {
                $table->string('unit_code', 3)->default('C62')->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->dropColumn(['tax_category_code', 'tax_exemption_reason']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('unit_code');
        });
    }
};
