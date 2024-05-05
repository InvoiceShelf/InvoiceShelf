<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use InvoiceShelf\Models\Company;

class AddSlugToCompanies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
