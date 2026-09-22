<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give a definition a say in whether it reaches the printed document.
     *
     * Until now nothing a user defined appeared on a PDF unless they edited a
     * Blade template by hand, with one exception: every `Item` field became a
     * column on the invoice and estimate tables whether it was wanted there or
     * not. So the two start from different places, and the backfill below
     * keeps each of them exactly where it was.
     */
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->string('placement', 20)->default('internal');
        });

        // Line-level definitions already printed, so they carry on printing.
        DB::table('custom_fields')
            ->where('model_type', 'Item')
            ->update(['placement' => 'document']);

        // Every read of a definition filters on the model it belongs to, and
        // every read of an answer filters on the record holding it; neither
        // pair was indexed.
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->index(['company_id', 'model_type'], 'custom_fields_company_model_index');
        });

        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->index(
                ['custom_field_valuable_type', 'custom_field_valuable_id'],
                'custom_field_values_valuable_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->dropIndex('custom_field_values_valuable_index');
        });

        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropIndex('custom_fields_company_model_index');
            $table->dropColumn('placement');
        });
    }
};
