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

        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('discount_val')->nullable()->change();
            $table->bigInteger('sub_total')->change();
            $table->bigInteger('total')->change();
            $table->bigInteger('tax')->change();
            $table->bigInteger('due_amount')->change();
            $table->bigInteger('base_discount_val')->nullable()->change();
            $table->bigInteger('base_sub_total')->change();
            $table->bigInteger('base_total')->change();
            $table->bigInteger('base_tax')->change();
            $table->bigInteger('base_due_amount')->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->bigInteger('discount_val')->change();
            $table->bigInteger('tax')->change();
            $table->bigInteger('total')->change();
            $table->bigInteger('base_discount_val')->change();
            $table->bigInteger('base_tax')->change();
            $table->bigInteger('base_total')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_val')->nullable()->change();
            $table->unsignedBigInteger('sub_total')->change();
            $table->unsignedBigInteger('total')->change();
            $table->unsignedBigInteger('due_amount')->change();
            $table->unsignedBigInteger('base_discount_val')->nullable()->change();
            $table->unsignedBigInteger('base_sub_total')->change();
            $table->unsignedBigInteger('base_total')->change();
            $table->unsignedBigInteger('base_tax')->change();
            $table->unsignedBigInteger('base_due_amount')->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_val')->change();
            $table->unsignedBigInteger('tax')->change();
            $table->unsignedBigInteger('total')->change();
            $table->unsignedBigInteger('base_discount_val')->change();
            $table->unsignedBigInteger('base_tax')->change();
            $table->unsignedBigInteger('base_total')->change();
        });
    }
};
