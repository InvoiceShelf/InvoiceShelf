<?php

use Illuminate\Database\Migrations\Migration;
use InvoiceShelf\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Setting::setSetting('version', '6.0.0');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }
};
