<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
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
