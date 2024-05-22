<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::update("UPDATE custom_field_values SET custom_field_valuable_type = REPLACE(custom_field_valuable_type, 'Crater', 'InvoiceShelf')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::update("UPDATE custom_field_values SET custom_field_valuable_type = REPLACE(custom_field_valuable_type, 'InvoiceShelf', 'Crater')");
    }
};
