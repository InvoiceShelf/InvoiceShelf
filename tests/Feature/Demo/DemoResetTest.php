<?php

// The public demo's scheduled rebuild. It runs migrate:fresh, so it lives in
// the isolated group (excluded from default runs, executed serially in CI) and
// forces the next test file to migrate again. Storage and public paths point
// at a scratch directory so the rebuild's file cleanup cannot touch the
// checkout.

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Modules\Marketplace\MarketplaceInstaller;
use App\Platform\Operations\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Modules\DemoProbe\Demo\DemoSeeder as ProbeDemoSeeder;
use Nwidart\Modules\Facades\Module;
use Silber\Bouncer\BouncerFacade;

uses()->group('isolated', 'serial-only');

beforeEach(function () {
    $this->scratch = sys_get_temp_dir().'/invoiceshelf-demo-'.uniqid();
    File::makeDirectory($this->scratch.'/storage/framework/cache', 0755, true);
    File::makeDirectory($this->scratch.'/storage/app/public/7', 0755, true);
    File::makeDirectory($this->scratch.'/storage/app/templates/pdf', 0755, true);
    File::makeDirectory($this->scratch.'/storage/app/Invoiceshelf-backups', 0755, true);
    File::makeDirectory($this->scratch.'/public/media/3', 0755, true);
    File::put($this->scratch.'/storage/app/public/7/logo.png', 'logo');
    File::put($this->scratch.'/storage/app/public/.gitignore', "*\n");
    File::put($this->scratch.'/storage/app/Invoiceshelf-backups/backup.zip', 'zip');
    File::put($this->scratch.'/storage/app/templates/pdf/custom.blade.php', 'template');
    File::put($this->scratch.'/storage/app/database.sqlite', '');
    File::put($this->scratch.'/public/media/3/receipt.pdf', 'pdf');

    $this->originalStorage = storage_path();
    $this->originalPublic = public_path();
    app()->useStoragePath($this->scratch.'/storage');
    app()->usePublicPath($this->scratch.'/public');

    config(['app.env' => 'demo', 'invoiceshelf.demo.modules' => '']);

    // migrate:fresh ends with VACUUM, which SQLite refuses inside the
    // transaction RefreshDatabase wraps each test in. Every test gets a new
    // in-memory database, so leaving it is safe.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

afterEach(function () {
    app()->useStoragePath($this->originalStorage);
    app()->usePublicPath($this->originalPublic);
    File::deleteDirectory($this->scratch);

    // The rebuild replaced the schema this file started with.
    RefreshDatabaseState::$migrated = false;
});

test('the demo is rebuilt without an administrator, with the demo owner and the portal customer', function () {
    expect(Artisan::call('reset:app', ['--force' => true]))->toBe(0);

    $owner = User::query()->sole();
    $company = $owner->companies()->sole();
    $portal = Customer::query()->where('email', 'customer@invoiceshelf.com')->sole();

    expect($owner->email)->toBe('demo@invoiceshelf.com')
        ->and($owner->role)->not->toBe('super admin')
        ->and(Hash::check('demo', $owner->password))->toBeTrue()
        ->and(BouncerFacade::scope()->onceTo($company->id, fn () => $owner->isAn('owner')))->toBeTrue()
        ->and($company->slug)->toBe('acme-inc')
        ->and($portal->company_id)->toBe($company->id)
        ->and($portal->enable_portal)->toBeTrue()
        ->and(Hash::check('demo', $portal->password))->toBeTrue()
        ->and($company->invoices()->count())->toBeGreaterThan(10)
        ->and(Setting::getSetting('profile_complete'))->toBe('COMPLETED')
        ->and(app()->isDownForMaintenance())->toBeFalse();
});

test('uploaded files are removed and templates are kept', function () {
    Artisan::call('reset:app', ['--force' => true]);

    expect(File::exists($this->scratch.'/storage/app/public/7/logo.png'))->toBeFalse()
        ->and(File::exists($this->scratch.'/storage/app/Invoiceshelf-backups'))->toBeFalse()
        ->and(File::exists($this->scratch.'/public/media/3'))->toBeFalse()
        ->and(File::exists($this->scratch.'/storage/app/public/.gitignore'))->toBeTrue()
        ->and(File::exists($this->scratch.'/storage/app/templates/pdf/custom.blade.php'))->toBeTrue()
        ->and(File::exists($this->scratch.'/storage/app/database.sqlite'))->toBeTrue();
});

test('the site comes back up when a step fails', function () {
    config(['invoiceshelf.demo.modules' => 'nowhere@9.9.9']);

    $marketplace = Mockery::mock(MarketplaceInstaller::class);
    $marketplace->shouldReceive('install')->andReturn(['success' => false, 'error' => 'offline']);
    app()->instance(MarketplaceInstaller::class, $marketplace);

    expect(fn () => Artisan::call('reset:app', ['--force' => true]))->toThrow(RuntimeException::class, 'Installing nowhere 9.9.9 failed: offline')
        ->and(app()->isDownForMaintenance())->toBeFalse();
});

test('a pinned module is installed after the company exists and its demo seeder is told which company to fill', function () {
    require_once base_path('tests/Fixtures/modules/DemoProbeSeeder.php');
    config(['invoiceshelf.demo.modules' => 'demo-probe@1.2.0']);

    $marketplace = Mockery::mock(MarketplaceInstaller::class);
    $marketplace->shouldReceive('install')->once()->with('demo-probe', '1.2.0', 'stable')->andReturn(['success' => true]);
    app()->instance(MarketplaceInstaller::class, $marketplace);

    $probe = Mockery::mock();
    $probe->shouldReceive('get')->with('slug')->andReturn('demo-probe');
    $probe->shouldReceive('get')->with('version')->andReturn(null);
    $probe->shouldReceive('getName')->andReturn('DemoProbe');
    Module::partialMock()->shouldReceive('scan')->andReturn([]);
    Module::shouldReceive('all')->andReturn([$probe]);

    Artisan::call('reset:app', ['--force' => true]);

    expect(ProbeDemoSeeder::$companyId)->toBe(User::query()->sole()->companies()->sole()->id);
});
