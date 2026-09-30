<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addInvitationRoleIds();
        $this->addGlobalAccessTables();
        $this->addGlobalRoleCombinationColumn();
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_company', 'include_global_roles')) {
            Schema::table('user_company', function (Blueprint $table): void {
                $table->dropColumn('include_global_roles');
            });
        }

        Schema::dropIfExists('user_restricted_companies');
        Schema::dropIfExists('user_global_roles');

        if (Schema::hasColumn('company_invitations', 'role_ids')) {
            Schema::table('company_invitations', function (Blueprint $table): void {
                $table->dropColumn('role_ids');
            });
        }

        // Role presets are deliberately not removed. This migration does not
        // create or rewrite preset data.
    }

    private function addInvitationRoleIds(): void
    {
        if (! Schema::hasColumn('company_invitations', 'role_ids')) {
            Schema::table('company_invitations', function (Blueprint $table): void {
                $table->json('role_ids')->nullable()->after('role_id');
            });
        }
    }

    private function addGlobalAccessTables(): void
    {
        if (! Schema::hasTable('user_global_roles')) {
            Schema::create('user_global_roles', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('role_preset_id')->index();
                $table->timestamps();

                $table->unique(['user_id', 'role_preset_id'], 'user_global_roles_unique');
            });
        }

        if (! Schema::hasTable('user_restricted_companies')) {
            Schema::create('user_restricted_companies', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('company_id')->index();
                $table->timestamps();

                $table->unique(['user_id', 'company_id'], 'user_restricted_companies_unique');
            });
        }
    }

    private function addGlobalRoleCombinationColumn(): void
    {
        if (! Schema::hasColumn('user_company', 'include_global_roles')) {
            Schema::table('user_company', function (Blueprint $table): void {
                $table->boolean('include_global_roles')->default(false)->after('company_id');
            });
        }
    }
};
