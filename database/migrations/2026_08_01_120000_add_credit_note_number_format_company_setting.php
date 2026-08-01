<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give every existing company a credit note number format.
     *
     * New companies get the setting from CompanyService::setupDefaultSettings(),
     * but installs created before independent credit note numbering have no such
     * row. CompanySetting::getSetting() then returns null, the format string is
     * empty, and SerialNumberService silently generates an empty document
     * number, so the data has to be backfilled rather than defaulted in code.
     */
    public function up(): void
    {
        Company::all()->each(function ($company) {
            $format = CompanySetting::getSetting('credit_note_number_format', $company->id);

            if ($format) {
                return;
            }

            CompanySetting::setSettings([
                'credit_note_number_format' => '{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:6}}',
            ], $company->id);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        CompanySetting::where('option', 'credit_note_number_format')->delete();
    }
};
