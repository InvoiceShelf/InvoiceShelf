<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('quantity');
        });
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->string('unit_name')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_name');
        });
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn('unit_name');
        });
    }
};
