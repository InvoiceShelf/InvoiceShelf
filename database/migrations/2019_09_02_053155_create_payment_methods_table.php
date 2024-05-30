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
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('company_id')->unsigned()->nullable();
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
