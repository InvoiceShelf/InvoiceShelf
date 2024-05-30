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
        Setting::setSetting('version', '4.0.1');
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
