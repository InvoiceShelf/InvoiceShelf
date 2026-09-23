<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
