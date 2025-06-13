<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\CompanySetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companies = \App\Models\Company::all();

        foreach ($companies as $company) {
            CompanySetting::setSettings([
                'invoice_email_bcc' => '',
            ], $company->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $companies = \App\Models\Company::all();

        foreach ($companies as $company) {
            CompanySetting::where('company_id', $company->id)
                ->where('key', 'invoice_email_bcc')
                ->delete();
        }
    }
};
