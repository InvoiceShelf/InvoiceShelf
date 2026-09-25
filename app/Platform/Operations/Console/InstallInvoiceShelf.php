<?php

namespace App\Platform\Operations\Console;

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Country;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Support\Formatting\DateFormatter;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\CurrenciesTableSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        {--language= : Its language, such as en (INSTALL_LANGUAGE)}
        {--date-format= : Its date format, such as d.m.Y (INSTALL_DATE_FORMAT)}
        {--fiscal-year= : Its fiscal year, such as 1-12 for January to December (INSTALL_FISCAL_YEAR)}
        {--admin-password-random : Give the administrator a random password nobody sees}
        {--send-welcome : Email the administrator a link to set their password}';

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
            'admin_password' => $options['admin-password-random']
                ? Str::password(40)
                : ($options['admin-password'] ?: config('installer.headless.admin_password') ?: $this->passwordFromFile()),
            'admin_name' => $options['admin-name'] ?: config('installer.headless.admin_name'),
            'company_name' => $options['company'] ?: config('installer.headless.company_name'),
            'currency' => strtoupper((string) ($options['currency'] ?: config('installer.headless.currency'))),
            'time_zone' => $options['timezone'] ?: config('installer.headless.time_zone'),
            'language' => $options['language'] ?: config('installer.headless.language'),
            'date_format' => $options['date-format'] ?: config('installer.headless.date_format'),
            'fiscal_year' => $options['fiscal-year'] ?: config('installer.headless.fiscal_year'),
        ];

        $validator = Validator::make($input, [
            'admin_email' => ['required', 'email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'admin_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'time_zone' => ['required', 'timezone'],
            'language' => ['required', 'string', 'max:10'],
            'date_format' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (DateFormatter::momentFormatFor((string) $value) === null) {
                    $fail("The date format {$value} is not one the app offers.");
                }
            }],
            'fiscal_year' => ['nullable', 'string', Rule::in(array_column(config('invoiceshelf.fiscal_years'), 'value'))],
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

            CompanySetting::setSettings(array_filter([
                'currency' => $currencyId,
                'time_zone' => $input['time_zone'],
                'language' => $input['language'],
                'carbon_date_format' => $input['date_format'],
                'moment_date_format' => $input['date_format'] ? DateFormatter::momentFormatFor($input['date_format']) : null,
                'fiscal_year' => $input['fiscal_year'],
            ], fn (mixed $value): bool => $value !== null && $value !== ''), $company->id);

            $admin->setSettings(['language' => $input['language']]);
        });

        InstallationState::setCurrentVersion();
        InstallationState::complete();

        $this->components->info("InvoiceShelf is installed. Sign in as {$input['admin_email']}.");

        if ($options['send-welcome']) {
            $this->sendWelcome($input['admin_email']);
        }

        return self::SUCCESS;
    }

    /**
     * The password in INSTALL_ADMIN_PASSWORD_FILE, trailing newline dropped.
     */
    private function passwordFromFile(): ?string
    {
        $file = config('installer.headless.admin_password_file');

        if (! $file || ! is_readable($file)) {
            return null;
        }

        return rtrim((string) file_get_contents($file), "\r\n");
    }

    /**
     * Email the administrator a link to set their password. The install has
     * already succeeded, so a mail failure is a warning, not a failure: the
     * owner can still use "forgot password".
     */
    private function sendWelcome(string $email): void
    {
        try {
            $status = Password::broker()->sendResetLink(['email' => $email]);
        } catch (\Throwable $failure) {
            $this->components->warn("The welcome mail could not be sent: {$failure->getMessage()}");

            return;
        }

        $status === Password::RESET_LINK_SENT
            ? $this->components->info("A link to set the password went to {$email}.")
            : $this->components->warn("The welcome mail was not sent ({$status}).");
    }
}
