<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Space\InstallUtils;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $user = User::factory()->create([
            'email' => 'demo@invoiceshelf.com',
            'name' => 'Demo User',
            'role' => 'super admin',
            'password' => 'demo',
        ]);

        // Create demo company
        $company = Company::factory()->create([
            'name' => 'Demo Company',
            'owner_id' => $user->id,
            'slug' => 'demo-company',
        ]);

        $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
        $company->save();
        $company->setupDefaultData();
        $user->companies()->attach($company->id);
        BouncerFacade::scope()->to($company->id);

        $user->assign('super admin');

        // Set default user settings
        $user->setSettings([
            'language' => 'en',
            'timezone' => 'UTC',
            'date_format' => 'DD-MM-YYYY',
            'currency_id' => 1, // USD
        ]);

        // Set company settings
        CompanySetting::setSettings([
            'currency' => 1,
            'date_format' => 'DD-MM-YYYY',
            'language' => 'en',
            'timezone' => 'UTC',
            'fiscal_year' => 'calendar_year',
            'tax_per_item' => false,
            'discount_per_item' => false,
            'invoice_prefix' => 'INV-',
            'estimate_prefix' => 'EST-',
            'payment_prefix' => 'PAY-',
        ], $company->id);

        // Create demo customers
        Customer::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        // Mark profile setup as complete
        Setting::setSetting('profile_complete', 'COMPLETED');

        // Create installation marker
        InstallUtils::createDbMarker();
    }
}
