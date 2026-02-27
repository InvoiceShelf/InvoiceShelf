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
        if (Schema::hasTable('interac_e_transfer_logs')) {
            return;
        }

        Schema::create('interac_e_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('message_id')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interac_e_transfer_logs');
    }
};
