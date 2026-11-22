<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements shown to everyone on the install: written here by a super
 * admin (or a hosting provider through the super-admin API), or copied from
 * the InvoiceShelf project feed. Each person can dismiss one for themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16)->default('local');
            // The feed's id for an announcement copied from it.
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('title');
            $table->text('body');
            $table->string('link_url', 500)->nullable();
            $table->string('link_label')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('audience', 16)->default('everyone');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('translations')->nullable();
            // A super admin can hide a feed announcement on this install.
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedInteger('user_id')->index();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
