<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment reminders: every reminder sent (or skipped) for an invoice, so each
 * scheduled one goes out once, and switches to pause them for an invoice or
 * for everything a customer owes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('invoice_id')->index();
            // Days from the due date it was scheduled for; null when sent by hand.
            $table->integer('offset_days')->nullable();
            $table->string('status', 16);
            $table->string('error')->nullable();
            $table->unsignedBigInteger('email_log_id')->nullable();
            $table->unsignedInteger('sent_by')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'offset_days']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('reminders_paused')->default(false);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->boolean('reminders_paused')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('reminders_paused');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('reminders_paused');
        });

        Schema::dropIfExists('invoice_reminders');
    }
};
