<?php

namespace Database\Seeders;

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Country;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Demo\DemoMode;
use App\Platform\Operations\Installation\Application\InstallationState;
use Illuminate\Database\Seeder;

/**
 * The public demo's company and its owner, the one account visitors sign in
 * with. Unlike DemoSeeder it uses no factories, since Faker is not installed
 * in production images, and it creates no super administrator: the demo
 * account owns one company and cannot reach the instance's own settings.
 *
 * RealisticDemoSeeder fills the company afterwards, and openPortal() then
 * gives one of its customers the portal sign-in the login page offers.
 */
class PublicDemoSeeder extends Seeder
{
    public const COMPANY = 'Acme Inc';

    public const PORTAL_CUSTOMER = 'Acme Corp';

    public function run(CompanyService $companies): void
    {
        $credentials = DemoMode::credentials();
        $usd = (int) Currency::query()->where('code', 'USD')->value('id');

        $owner = User::query()->create([
            'email' => $credentials['email'],
            'name' => 'Demo User',
            'password' => $credentials['password'],
            'role' => 'user',
        ]);

        $company = $companies->createFor($owner, [
            'name' => self::COMPANY,
            'vat_id' => 'US123456789',
            'tax_id' => '84-1234567',
        ], $usd);

        $company->address()->create([
            'address_street_1' => '1180 Market Street',
            'address_street_2' => 'Suite 400',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'phone' => '+1 415 555 0142',
            'country_id' => Country::query()->where('code', 'US')->value('id'),
        ]);

        CompanySetting::setSettings([
            'currency' => $usd,
            'time_zone' => 'UTC',
            'fiscal_year' => '1-12',
            'carbon_date_format' => 'Y/m/d',
            'moment_date_format' => 'YYYY/MM/DD',
        ], $company->id);

        $owner->setSettings(['language' => 'en']);

        InstallationState::setCurrentVersion();
        InstallationState::complete();
    }

    /**
     * Give the demo's portal customer the sign-in the login page offers.
     */
    public static function openPortal(): void
    {
        $credentials = DemoMode::credentials();
        $companyId = User::query()->where('email', $credentials['email'])->firstOrFail()->companies()->value('companies.id');

        $customer = Customer::query()->where('company_id', $companyId)->where('name', self::PORTAL_CUSTOMER)->first()
            ?? Customer::query()->where('company_id', $companyId)->orderBy('id')->firstOrFail();

        $customer->email = $credentials['portal_email'];
        $customer->enable_portal = true;
        $customer->password = $credentials['portal_password'];
        $customer->save();
    }
}
