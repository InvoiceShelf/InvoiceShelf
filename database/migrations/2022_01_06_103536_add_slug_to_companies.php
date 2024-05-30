<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companies = Company::where('slug', null)->get();

        if ($companies) {
            foreach ($companies as $company) {
                $company->slug = Str::slug($company->name);
                $company->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
