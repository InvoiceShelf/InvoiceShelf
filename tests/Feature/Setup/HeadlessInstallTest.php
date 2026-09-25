<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Silber\Bouncer\BouncerFacade;

/*
 * `php artisan invoiceshelf:install` on an empty, migrated database: nothing
 * seeded, no users, the installer still open.
 */

function install(array $options = []): int
{
    return Artisan::call('invoiceshelf:install', array_merge([
        '--admin-email' => 'owner@acme.test',
        '--admin-password' => 'correct horse battery',
        '--admin-name' => 'Ada Owner',
        '--company' => 'Acme GmbH',
        '--currency' => 'eur',
        '--timezone' => 'Europe/Berlin',
        '--language' => 'de',
    ], $options));
}

test('it installs a super administrator and their company, and closes the installer', function () {
    expect(install())->toBe(0);

    $admin = User::query()->sole();
    $company = $admin->companies()->sole();

    expect($admin->email)->toBe('owner@acme.test')
        ->and($admin->role)->toBe('super admin')
        ->and(Hash::check('correct horse battery', $admin->password))->toBeTrue()
        ->and($company->name)->toBe('Acme GmbH')
        ->and($company->slug)->toBe('acme-gmbh')
        ->and($company->unique_hash)->not->toBeEmpty()
        ->and($company->owner_id)->toBe($admin->id)
        ->and(BouncerFacade::scope()->onceTo($company->id, fn () => $admin->isAn('owner')))->toBeTrue()
        ->and(CompanySetting::getSetting('currency', $company->id))->toBe((string) Currency::where('code', 'EUR')->value('id'))
        ->and(CompanySetting::getSetting('time_zone', $company->id))->toBe('Europe/Berlin')
        ->and(CompanySetting::getSetting('language', $company->id))->toBe('de')
        ->and(Setting::getSetting('profile_complete'))->toBe('COMPLETED')
        ->and(Setting::getSetting('version'))->toBe(trim(file_get_contents(base_path('version.md'))))
        ->and(User::query()->where('email', 'admin@invoiceshelf.com')->exists())->toBeFalse();
});

test('it does nothing once installed', function () {
    install();

    expect(install(['--admin-email' => 'someone-else@acme.test', '--company' => 'Other']))->toBe(0)
        ->and(User::query()->count())->toBe(1)
        ->and(User::query()->value('email'))->toBe('owner@acme.test');
});

test('it reads its settings from the environment', function () {
    config([
        'installer.headless.admin_email' => 'env@acme.test',
        'installer.headless.admin_password' => 'from the environment',
        'installer.headless.company_name' => 'Env Co',
        'installer.headless.currency' => 'USD',
    ]);

    expect(Artisan::call('invoiceshelf:install'))->toBe(0)
        ->and(User::query()->value('email'))->toBe('env@acme.test')
        ->and(User::query()->first()->companies()->value('name'))->toBe('Env Co');
});

test('it refuses to install without an administrator or with a currency that does not exist', function (array $options) {
    expect(install($options))->toBe(1)
        ->and(InstallationState::isComplete())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
})->with([
    'no email' => [['--admin-email' => '']],
    'short password' => [['--admin-password' => 'short']],
    'bad time zone' => [['--timezone' => 'Mars/Olympus']],
    'unknown currency' => [['--currency' => 'XXX']],
]);

test('a random password never leaves the host, and the welcome mail lets the owner set one', function () {
    Notification::fake();

    expect(install(['--admin-password' => null, '--admin-password-random' => true, '--send-welcome' => true]))->toBe(0);

    $admin = User::query()->sole();

    expect($admin->password)->not->toBeEmpty()
        ->and(Artisan::output())->not->toContain('password:');

    Notification::assertSentTo($admin, MailResetPasswordNotification::class);
});

test('the welcome mail can go out with a chosen password too', function () {
    Notification::fake();

    expect(install(['--send-welcome' => true]))->toBe(0);

    Notification::assertSentTo(User::query()->sole(), MailResetPasswordNotification::class);
});

test('it sets the company date format and fiscal year', function () {
    expect(install(['--date-format' => 'd.m.Y', '--fiscal-year' => '4-3']))->toBe(0);

    $company = User::query()->sole()->companies()->sole();

    expect(CompanySetting::getSetting('carbon_date_format', $company->id))->toBe('d.m.Y')
        ->and(CompanySetting::getSetting('moment_date_format', $company->id))->toBe('DD.MM.YYYY')
        ->and(CompanySetting::getSetting('fiscal_year', $company->id))->toBe('4-3');
});

test('it refuses a date format or fiscal year the app does not offer', function (array $options) {
    expect(install($options))->toBe(1)
        ->and(InstallationState::isComplete())->toBeFalse();
})->with([
    'date format' => [['--date-format' => 'Y.d.m']],
    'fiscal year' => [['--fiscal-year' => '13-12']],
]);

test('it reads the password from a file', function () {
    $file = tempnam(sys_get_temp_dir(), 'pw');
    file_put_contents($file, "from a secret file\n");
    config(['installer.headless.admin_password_file' => $file]);

    try {
        expect(install(['--admin-password' => null]))->toBe(0)
            ->and(Hash::check('from a secret file', User::query()->sole()->password))->toBeTrue();
    } finally {
        @unlink($file);
    }
});
