<?php

// Domain behavioural suite — Money: the currency catalogue and its refresh.

use App\Domains\Accounts\Models\User;
use App\Domains\Money\Application\CurrencyCatalog;
use App\Domains\Money\Application\CurrencyService;
use App\Domains\Money\Models\Currency;
use App\Platform\Operations\Update\Updater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $this->catalog = app(CurrencyCatalog::class);
    $this->currencies = app(CurrencyService::class);
});

it('offers every circulating currency exactly once', function () {
    $codes = $this->catalog->codes();

    expect($codes)->toHaveCount(count(array_unique($codes)))
        ->and($codes)->toContain('HUF', 'KRW', 'GEL', 'XCG', 'KZT', 'UZS', 'JOD', 'ETB');

    foreach ($this->catalog->all() as $entry) {
        expect($entry['code'])->toMatch('/^[A-Z]{3}$/')
            ->and($entry['name'])->not->toBeEmpty()
            ->and($entry['symbol'])->not->toBeEmpty()
            // The ISO minor unit. Nothing in circulation uses any other.
            ->and($entry['precision'])->toBeIn([0, 2, 3]);
    }
});

it('keeps the codes ISO retired, because records still point at them', function () {
    expect($this->catalog->codes())->toContain('HRK', 'ANG');
});

it('records the minor unit a currency actually has', function () {
    $precision = collect($this->catalog->all())->pluck('precision', 'code');

    // Zero-decimal, though the seeder shipped four of them as two.
    expect($precision['CLP'])->toBe(0)
        ->and($precision['XAF'])->toBe(0)
        ->and($precision['XOF'])->toBe(0)
        ->and($precision['RWF'])->toBe(0)
        ->and($precision['JPY'])->toBe(0)
        ->and($precision['KRW'])->toBe(0);

    // Three-decimal Gulf dinars, two of which shipped as two.
    expect($precision['IQD'])->toBe(3)
        ->and($precision['OMR'])->toBe(3)
        ->and($precision['KWD'])->toBe(3)
        ->and($precision['BHD'])->toBe(3);

    // And two that shipped as zero and do have subunits.
    expect($precision['PKR'])->toBe(2)
        ->and($precision['JMD'])->toBe(2);
});

it('seeds the whole catalogue once, with no code twice', function () {
    expect(Currency::count())->toBe(count($this->catalog->codes()));

    $duplicated = DB::table('currencies')
        ->select('code')
        ->groupBy('code')
        ->havingRaw('count(*) > 1')
        ->pluck('code');

    expect($duplicated)->toBeEmpty();
});

it('plants a currency the installation is missing', function () {
    Currency::query()->where('code', 'HUF')->delete();

    $result = $this->currencies->sync();

    expect($result['added'])->toBe(['HUF'])
        ->and(Currency::query()->where('code', 'HUF')->value('name'))->toBe('Hungarian Forint');
});

it('corrects a currency whose data is wrong', function () {
    Currency::query()->where('code', 'CLP')->update(['precision' => 2]);

    $result = $this->currencies->sync();

    expect($result['updated'])->toBe(['CLP'])
        ->and(Currency::query()->where('code', 'CLP')->value('precision'))->toBe(0);
});

it('leaves a currency it does not know about alone', function () {
    Currency::create([
        'code' => 'ZZZ', 'name' => 'Module Credits', 'symbol' => 'ZZ',
        'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.',
    ]);

    $this->currencies->sync();

    expect(Currency::query()->where('code', 'ZZZ')->value('name'))->toBe('Module Credits');
});

it('changes nothing when run a second time', function () {
    $result = $this->currencies->sync();

    expect($result['added'])->toBeEmpty()
        ->and($result['updated'])->toBeEmpty();
});

it('collapses currency rows an earlier release duplicated', function () {
    // Undo the guard so the state that shipped can be reproduced.
    Schema::table('currencies', function ($table): void {
        $table->dropUnique(['code']);
    });

    $original = Currency::query()->where('code', 'QAR')->firstOrFail();
    $duplicate = Currency::create([
        'code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'QR',
        'precision' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.',
    ]);

    $company = User::where('role', 'super admin')->first()->companies()->first();
    DB::table('invoices')->whereNotNull('id')->limit(1)->update(['currency_id' => $duplicate->id]);
    DB::table('company_settings')
        ->where('company_id', $company->id)
        ->where('option', 'currency')
        ->update(['value' => (string) $duplicate->id]);

    (require database_path('migrations/2026_09_22_120000_deduplicate_currencies.php'))->up();

    expect(Currency::query()->where('code', 'QAR')->count())->toBe(1)
        ->and(Currency::query()->find($duplicate->id))->toBeNull()
        ->and(DB::table('invoices')->where('currency_id', $duplicate->id)->count())->toBe(0)
        ->and(DB::table('company_settings')
            ->where('company_id', $company->id)
            ->where('option', 'currency')
            ->value('value'))->toBe((string) $original->id);
});

it('refreshes the catalogue for a super admin', function () {
    Currency::query()->where('code', 'KRW')->delete();

    Sanctum::actingAs(User::where('role', 'super admin')->first(), ['*']);

    postJson('/api/v1/super-admin/currencies/refresh')
        ->assertOk()
        ->assertJsonPath('added', ['KRW'])
        ->assertJsonPath('total', count($this->catalog->codes()));
});

it('refuses the refresh to anyone else', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'user']), ['*']);

    postJson('/api/v1/super-admin/currencies/refresh')->assertForbidden();

    expect(Currency::count())->toBe(count($this->catalog->codes()));
});

it('syncs from the command line, for installations that never see the button', function () {
    Currency::query()->where('code', 'HUF')->delete();

    $this->artisan('currencies:sync')
        ->expectsOutputToContain('1 added')
        ->expectsOutputToContain('HUF')
        ->assertSuccessful();

    expect(Currency::query()->where('code', 'HUF')->exists())->toBeTrue();

    $this->artisan('currencies:sync')
        ->expectsOutputToContain('already up to date')
        ->assertSuccessful();
});

it('carries a release\'s new currencies into an upgraded installation', function () {
    Currency::query()->where('code', 'KRW')->delete();

    Updater::migrateUpdate();

    expect(Currency::query()->where('code', 'KRW')->value('name'))->toBe('South Korean Won');
});
