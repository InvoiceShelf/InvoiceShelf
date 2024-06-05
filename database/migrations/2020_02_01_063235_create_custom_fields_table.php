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
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug');
            $table->string('label');
            $table->string('model_type');
            $table->string('type');
            $table->string('placeholder')->nullable();
            $table->json('options')->nullable();
            $table->boolean('boolean_answer')->nullable();
            $table->date('date_answer')->nullable();
            $table->time('time_answer')->nullable();
            $table->text('string_answer')->nullable();
            $table->unsignedBigInteger('number_answer')->nullable();
            $table->dateTime('date_time_answer')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedBigInteger('order')->default(1);
            $table->integer('company_id')->unsigned();
            $table->foreign('company_id')->references('id')->on('companies');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['company_id']);
            }
        });
        Schema::dropIfExists('custom_fields');
    }
};
