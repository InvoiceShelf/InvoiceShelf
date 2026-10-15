<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notices name the company they belong to, so the bell shows only the
 * workspace being looked at, and the record they are about, so they can be
 * found again (to skip a repeat, or to clear them when the record goes).
 * A notice with no company is a platform notice, shown everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->unsignedInteger('company_id')->nullable()->after('notifiable_id');
            $table->string('subject_type')->nullable()->after('company_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');

            $table->index(['notifiable_type', 'notifiable_id', 'company_id', 'read_at'], 'notifications_inbox_index');
            $table->index(['subject_type', 'subject_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_inbox_index');
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn(['company_id', 'subject_type', 'subject_id']);
        });
    }
};
