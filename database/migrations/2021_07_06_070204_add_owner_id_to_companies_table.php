<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('slug')->nullable();
            $table->unsignedInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('users');
        });

        $user = User::where('role', 'super admin')->first();

        $companies = Company::all();

        if ($companies && $user) {
            foreach ($companies as $company) {
                $company->owner_id = $user->id;
                $company->slug = Str::slug($company->name);
                $company->save();

                $company->setupRoles();
                $user->assign('super admin');

                $users = User::where('role', 'admin')->get();
                $users->map(function ($user) {
                    $user->assign('super admin');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('slug');
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['owner_id']);
            }
        });
    }
};
