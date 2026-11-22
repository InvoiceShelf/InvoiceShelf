<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Country;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Intl\Countries;

use function Pest\Laravel\getJson;

/**
 * #957: the list inherited from Crater filed the Isle of Man, Jersey,
 * Guernsey and Timor-Leste under codes that are not ISO 3166-1, so their
 * addresses printed "Man (Isle of)" and the like, and it lacked seven current
 * countries, Montenegro among them.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

/** Codes kept for addresses that may still point at them; no ISO code maps onto them. */
const LEGACY_COUNTRY_CODES = ['AN', 'XA', 'XG', 'YU'];

function runCountryCodeMigration(): void
{
    (require database_path('migrations/2026_10_27_173454_correct_country_codes_and_names.php'))->up();
}

test('every country a new install ships is an ISO country, and none is missing', function () {
    $codes = Country::query()->pluck('code')->all();

    expect(array_values(array_filter($codes, fn (string $code): bool => ! in_array($code, LEGACY_COUNTRY_CODES, true) && ! Countries::exists($code))))->toBe([])
        ->and(array_values(array_diff(Countries::getCountryCodes(), $codes)))->toBe([])
        ->and(Country::query()->where('code', 'IM')->value('name'))->toBe('Isle of Man')
        ->and(Country::query()->where('code', 'ME')->value('name'))->toBe('Montenegro')
        ->and(Country::query()->where('code', 'KR')->value('name'))->toBe('South Korea');
});

test('an existing install has its countries recoded, renamed and completed in place', function () {
    // The list as 2.x and earlier 3.x installs hold it.
    DB::table('countries')->whereIn('code', ['AX', 'BL', 'BQ', 'CW', 'ME', 'MF', 'SX'])->delete();
    DB::table('countries')->where('code', 'IM')->update(['code' => 'XM', 'name' => 'Man (Isle of)']);
    DB::table('countries')->where('code', 'JE')->update(['code' => 'XJ', 'name' => 'Bailiwick of Jersey']);
    DB::table('countries')->where('code', 'GG')->update(['code' => 'XU', 'name' => 'Guernsey and Alderney']);
    DB::table('countries')->where('code', 'TL')->update(['code' => 'TP', 'name' => 'East Timor']);
    DB::table('countries')->where('code', 'KR')->update(['name' => 'Korea South']);
    DB::table('countries')->where('code', 'CZ')->update(['name' => 'Czech Republic (ours)']);

    $isleOfMan = (int) DB::table('countries')->where('code', 'XM')->value('id');
    $address = Address::factory()->create(['country_id' => $isleOfMan]);

    runCountryCodeMigration();

    expect(DB::table('countries')->where('id', $isleOfMan)->first())
        ->code->toBe('IM')
        ->name->toBe('Isle of Man')
        ->and(DB::table('countries')->where('code', 'GG')->value('name'))->toBe('Guernsey')
        ->and(DB::table('countries')->where('code', 'TL')->value('name'))->toBe('Timor-Leste')
        ->and(DB::table('countries')->where('code', 'KR')->value('name'))->toBe('South Korea')
        // Names an owner changed by hand are theirs to keep.
        ->and(DB::table('countries')->where('code', 'JE')->value('name'))->toBe('Bailiwick of Jersey')
        ->and(DB::table('countries')->where('code', 'CZ')->value('name'))->toBe('Czech Republic (ours)')
        ->and(DB::table('countries')->where('code', 'ME')->value('id'))->toBeGreaterThan(246);

    App::setLocale('en');
    expect($address->fresh()->country_name)->toBe('Isle of Man');
});

test('running it again changes nothing', function () {
    $before = DB::table('countries')->orderBy('id')->get()->toArray();

    runCountryCodeMigration();
    runCountryCodeMigration();

    expect(DB::table('countries')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('a fresh install is left to the seeder', function () {
    // Addresses are the only rows that point at a country.
    DB::table('addresses')->delete();
    DB::table('countries')->delete();

    runCountryCodeMigration();

    expect(DB::table('countries')->count())->toBe(0);
});

test('the picker lists countries by name, accents folded', function () {
    $names = collect(getJson('api/v1/countries')->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe($names->sortBy(fn (string $name): string => Str::lower(Str::ascii($name)))->values()->all())
        ->and($names->take(2)->all())->toBe(['Afghanistan', 'Åland Islands'])
        ->and($names)->toContain('Isle of Man')->not->toContain('Man (Isle of)');
});
