<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
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
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('token')->unique()->nullable();
        });

        $user = User::where('role', 'super admin')->first();

        if ($user) {
            $settings = [
                'automatically_expire_public_links' => 'Yes',
                'link_expiry_days' => 7,
            ];

            $companies = Company::all();

            foreach ($companies as $company) {
                CompanySetting::setSettings($settings, $company->id);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
