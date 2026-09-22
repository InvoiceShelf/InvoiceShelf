<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two things a definition can now say about itself: where its answers
     * appear, and what counts as a valid one.
     *
     * Each column is added only if it is absent. This file was called
     * `add_placement_to_custom_fields_table` for a day and a half, long
     * enough for developers on the branch to have run it, and renaming it
     * makes Laravel offer it again against a `migrations` table that still
     * holds the old name. The guards mean that costs nobody a rollback.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('custom_fields', 'placement')) {
            Schema::table('custom_fields', function (Blueprint $table) {
                $table->string('placement', 20)->default('internal');
            });

            // Nothing a user defined reached a PDF unless they edited a Blade
            // template, with one exception: every `Item` field became a column
            // on the invoice and estimate tables whether it was wanted there
            // or not. The two start from different places, so each is left
            // exactly where it was.
            DB::table('custom_fields')
                ->where('model_type', 'Item')
                ->update(['placement' => 'document']);
        }

        if (! Schema::hasColumn('custom_fields', 'validation')) {
            Schema::table('custom_fields', function (Blueprint $table) {
                // A small description rather than a rule string: bounds on
                // length and value, and an optional pattern for the rare case
                // that needs one. Null means the answer is unconstrained,
                // which is how every definition behaved until now.
                $table->json('validation')->nullable();
            });
        }

        // Every read of a definition filters on the model it belongs to, and
        // every read of an answer filters on the record holding it; neither
        // pair was indexed.
        if (! $this->hasIndex('custom_fields', 'custom_fields_company_model_index')) {
            Schema::table('custom_fields', function (Blueprint $table) {
                $table->index(['company_id', 'model_type'], 'custom_fields_company_model_index');
            });
        }

        if (! $this->hasIndex('custom_field_values', 'custom_field_values_valuable_index')) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                $table->index(
                    ['custom_field_valuable_type', 'custom_field_valuable_id'],
                    'custom_field_values_valuable_index'
                );
            });
        }
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
            $table->dropColumn(['placement', 'validation']);
        });
    }

    /**
     * Whether a named index is already on the table.
     *
     * Asked through the schema builder rather than a vendor-specific query,
     * because the suite runs on SQLite and installs run on MySQL or Postgres.
     */
    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
