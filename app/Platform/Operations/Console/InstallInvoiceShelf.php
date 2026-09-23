<?php

namespace App\Platform\Operations\Console;

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Country;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Installation\Application\InstallationState;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\CurrenciesTableSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Installs InvoiceShelf without the web installer, for servers that are set
 * up by configuration rather than by hand: it migrates the database, seeds
 * the currencies and countries, creates the super administrator and the
 * first company, and closes the installer.
 *
 * Unlike the web installer it never creates the default administrator, so
 * there is no well-known password to change afterwards. On an installed
 * application it does nothing, so it is safe to run on every start.
 */
class InstallInvoiceShelf extends Command
{
    protected $signature = 'invoiceshelf:install
        {--admin-email= : The super administrator\'s email (INSTALL_ADMIN_EMAIL)}
        {--admin-password= : Their password, at least 8 characters (INSTALL_ADMIN_PASSWORD)}
        {--admin-name= : Their name (INSTALL_ADMIN_NAME)}
        {--company= : The first company\'s name (INSTALL_COMPANY_NAME)}
        {--currency= : Its currency, an ISO code such as EUR (INSTALL_CURRENCY)}
        {--timezone= : Its time zone, such as Europe/Berlin (INSTALL_TIMEZONE)}
        {--language= : Its language, such as en (INSTALL_LANGUAGE)}';

    protected $description = 'Install InvoiceShelf without the web installer';

    public function handle(CompanyService $companies): int
    {
        if (InstallationState::isComplete()) {
            $this->components->info('InvoiceShelf is already installed; nothing to do.');

            return self::SUCCESS;
        }

        $options = $this->options();

        $input = [
            'admin_email' => $options['admin-email'] ?: config('installer.headless.admin_email'),
            'admin_password' => $options['admin-password'] ?: config('installer.headless.admin_password'),
            'admin_name' => $options['admin-name'] ?: config('installer.headless.admin_name'),
            'company_name' => $options['company'] ?: config('installer.headless.company_name'),
            'currency' => strtoupper((string) ($options['currency'] ?: config('installer.headless.currency'))),
            'time_zone' => $options['timezone'] ?: config('installer.headless.time_zone'),
            'language' => $options['language'] ?: config('installer.headless.language'),
        ];

        $validator = Validator::make($input, [
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'admin_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'time_zone' => ['required', 'timezone'],
            'language' => ['required', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->task('Migrating the database', fn () => Artisan::call('migrate', ['--force' => true]) === 0);

        if (Currency::query()->count() === 0) {
            $this->components->task('Adding currencies', fn () => Artisan::call('db:seed', ['--class' => CurrenciesTableSeeder::class, '--force' => true]) === 0);
        }

        if (Country::query()->count() === 0) {
            $this->components->task('Adding countries', fn () => Artisan::call('db:seed', ['--class' => CountriesTableSeeder::class, '--force' => true]) === 0);
        }

        $currencyId = Currency::query()->where('code', $input['currency'])->value('id');

        if (! $currencyId) {
            $this->components->error("There is no currency with the code {$input['currency']}.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($companies, $input, $currencyId): void {
            $admin = User::query()->where('email', $input['admin_email'])->first()
                ?? User::query()->create([
                    'email' => $input['admin_email'],
                    'name' => $input['admin_name'],
                    'password' => $input['admin_password'],
                    'role' => 'super admin',
                ]);

            $admin->update(['role' => 'super admin']);

            $company = $admin->companies()->first()
                ?? $companies->createFor($admin, ['name' => $input['company_name']], (int) $currencyId);

            CompanySetting::setSettings([
                'currency' => $currencyId,
                'time_zone' => $input['time_zone'],
                'language' => $input['language'],
            ], $company->id);

            $admin->setSettings(['language' => $input['language']]);
        });

        InstallationState::setCurrentVersion();
        InstallationState::complete();

        $this->components->info("InvoiceShelf is installed. Sign in as {$input['admin_email']}.");

        return self::SUCCESS;
    }
}
