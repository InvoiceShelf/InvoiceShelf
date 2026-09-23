<?php

namespace Database\Seeders;

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Country;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Facades\Hashids;
use App\Platform\Operations\Models\Setting;
use App\Support\Hashids\HashidConnection;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;

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
            'name' => 'Acme Inc',
            'owner_id' => $user->id,
            'slug' => 'acme-inc',
            'vat_id' => 'US123456789',
            'tax_id' => '84-1234567',
        ]);

        $company->unique_hash = Hashids::connection(HashidConnection::Company->value)->encode($company->id);
        $company->save();
        app(CompanyService::class)->setupDefaults($company);

        $this->createCompanyAddress($company);
        $user->companies()->attach($company->id);
        BouncerFacade::scope()->to($company->id);

        $user->assign('owner');

        // Resolve USD by code rather than trusting an id. A database upgraded
        // from 2.x carries whatever order its own migrations produced, and on
        // one of those id 1 is the Algerian Dinar, so the demo would price
        // everything in "DA".
        $currencyId = Currency::where('code', 'USD')->value('id') ?? 1;

        // Set default user settings
        $user->setSettings([
            'language' => 'en',
            'time_zone' => 'UTC',
            'date_format' => 'DD-MM-YYYY',
            'currency_id' => $currencyId,
        ]);

        // Set company settings
        CompanySetting::setSettings([
            'currency' => $currencyId,
            'date_format' => 'DD-MM-YYYY',
            'language' => 'en',
            'time_zone' => 'UTC',
            'fiscal_year' => 'calendar_year',
            // The forms show document-level tax and discount controls for NO
            // only; a false here stored "0" and hid both.
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
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
    }

    /**
     * Give the demo company a real postal address.
     *
     * Without one, Invoice::getCompanyAddress() returns false outright and the
     * company block is omitted from every document — the name only appears
     * because the template falls back to it when there is no logo.
     *
     * Created through the relation, as CompaniesController does, so company_id
     * is set and type/user_id/customer_id stay null. That matters: Company's
     * address() is an unscoped hasOne, so any address carrying this company_id
     * would be picked up as the company's own. Customer addresses deliberately
     * leave company_id null for the same reason.
     *
     * The fields chosen are the ones the default address format actually
     * renders (CompanyService::setupDefaultSettings) — country comes from
     * country_id via the relation, not a string.
     */
    private function createCompanyAddress(Company $company): void
    {
        $company->address()->create([
            'address_street_1' => '1180 Market Street',
            'address_street_2' => 'Suite 400',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'phone' => '+1 415 555 0142',
            'country_id' => Country::where('code', 'US')->value('id'),
        ]);
    }
}
