<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoices keep why their last run failed, and whether to email the
 * person who set them up, as recurring bills and expenses do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->string('last_error')->nullable();
            $table->boolean('notify_creator')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->dropColumn(['last_error', 'notify_creator']);
        });
    }
};
