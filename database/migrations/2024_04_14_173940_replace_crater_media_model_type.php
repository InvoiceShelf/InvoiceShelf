<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use InvoiceShelf\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::update("UPDATE media SET model_type = REPLACE(model_type, 'Crater', 'InvoiceShelf')");
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::update("UPDATE media SET model_type = REPLACE(model_type, 'InvoiceShelf', 'Crater')");
        } catch (\Exception $e) {
        }
    }
};
