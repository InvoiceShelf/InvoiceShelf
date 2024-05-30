<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::setSetting('version', '6.0.5');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
