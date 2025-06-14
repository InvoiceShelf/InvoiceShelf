<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companies = Company::all();

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
        $companies = Company::all();

        foreach ($companies as $company) {
            CompanySetting::where('company_id', $company->id)
                ->where('key', 'invoice_email_bcc')
                ->delete();
        }
    }
};
