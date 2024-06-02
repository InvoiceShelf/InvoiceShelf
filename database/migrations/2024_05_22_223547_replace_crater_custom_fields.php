<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('custom_field_values')->update([ 'custom_field_valuable_type' => DB::raw("REPLACE(custom_field_valuable_type, 'Crater', 'InvoiceShelf')") ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('custom_field_values')->update([ 'custom_field_valuable_type' => DB::raw("REPLACE(custom_field_valuable_type, 'InvoiceShelf', 'Crater')") ]);
    }
};
