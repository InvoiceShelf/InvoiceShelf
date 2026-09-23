<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per AI client a user connected: which company it works in and
     * whether it may write. Keyed by user and OAuth client, so re-consenting
     * from the same client replaces the row rather than adding one.
     */
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('company_id')->index();
            $table->uuid('oauth_client_id')->index();
            $table->string('access', 16);
            // Display snapshots, so the list still reads sensibly after the
            // client registration itself has been pruned.
            $table->string('client_name')->nullable();
            $table->string('redirect_host')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
    }
};
