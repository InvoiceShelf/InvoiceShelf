<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The accounts at outside identity providers that sign users in (single
     * sign-on), one row per link. `type` is the protocol, "oidc" today, so
     * another one can be added without touching users. `issuer` and
     * `subject` name the account there (for OpenID Connect, the issuer URL
     * and the "sub" claim). See SsoIdentity and OidcLoginService.
     *
     * Two unique indexes keep the links straight on every database, also
     * when two sign-ins race: an account links to one user only (type,
     * issuer, subject), and a user has one account per protocol (user_id,
     * type), which also serves as the index on user_id.
     *
     * user_id matches users.id (INT UNSIGNED) and, as everywhere in this
     * schema, has no database constraint: MemberService deletes a user's
     * rows when it deletes the user.
     */
    public function up(): void
    {
        Schema::create('sso_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type', 32);
            $table->string('issuer');
            $table->string('subject');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'issuer', 'subject']);
            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sso_identities');
    }
};
