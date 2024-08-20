<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::setSetting('version', '1.3.0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::setSetting('version', '1.2.2');
    }
};
