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
    public const COMPANY = 'Acme GmbH';

    public const VAT_ID = 'DE123456789';

    public const TAX_ID = '27/123/45678';

    /**
     * The company's Berlin address; RealisticDemoSeeder writes the same one
     * onto a local demo company.
     *
     * @var array<string, string>
     */
    public const ADDRESS = [
        'address_street_1' => 'Friedrichstraße 68',
        'city' => 'Berlin',
        'zip' => '10117',
        'phone' => '+49 30 5550100',
    ];

    public const PORTAL_CUSTOMER = 'Nordwind Logistik GmbH';

    public function run(CompanyService $companies): void
    {
        $credentials = DemoMode::credentials();
        $eur = (int) Currency::query()->where('code', 'EUR')->value('id');

        $owner = User::query()->create([
            'email' => $credentials['email'],
            'name' => 'Demo User',
            'password' => $credentials['password'],
            'role' => 'user',
        ]);

        $company = $companies->createFor($owner, [
            'name' => self::COMPANY,
            'vat_id' => self::VAT_ID,
            'tax_id' => self::TAX_ID,
        ], $eur);

        $company->address()->create([
            ...self::ADDRESS,
            'country_id' => Country::query()->where('code', 'DE')->value('id'),
        ]);

        CompanySetting::setSettings([
            'currency' => $eur,
            'time_zone' => 'Europe/Berlin',
            'fiscal_year' => '1-12',
            'carbon_date_format' => 'd.m.Y',
            'moment_date_format' => 'DD.MM.YYYY',
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
        $companyId = DemoMode::company()?->id;

        $customer = Customer::query()->where('company_id', $companyId)->where('name', self::PORTAL_CUSTOMER)->first()
            ?? Customer::query()->where('company_id', $companyId)->orderBy('id')->firstOrFail();

        $customer->email = $credentials['portal_email'];
        $customer->enable_portal = true;
        $customer->password = $credentials['portal_password'];
        $customer->save();
    }
}
