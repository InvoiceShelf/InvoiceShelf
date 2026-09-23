<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The four tables Passport's authorization code and refresh token grants
     * read and write, adapted from the migrations Passport publishes.
     *
     * Two departures from Passport's own: `user_id` is an unsigned integer,
     * because `users.id` is an increments column, and no foreign keys are
     * declared, as elsewhere in this schema. The device code table is left
     * out because the device grant is switched off.
     */
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamps();
        });

        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->unsignedInteger('user_id')->index();
            $table->uuid('client_id')->index();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->uuid('client_id')->index();
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->char('access_token_id', 80)->index();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_clients');
    }
};
