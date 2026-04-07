<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->decimal('percent', 5, 3)->nullable()->change();
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->decimal('percent', 5, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->decimal('percent', 5, 2)->nullable()->change();
        });

        Schema::table('taxes', function (Blueprint $table) {
            $table->decimal('percent', 5, 2)->nullable()->change();
        });
    }
};
