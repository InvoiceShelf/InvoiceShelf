<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public const VERSION = '3.2.0';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::setSetting('version', self::VERSION);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
