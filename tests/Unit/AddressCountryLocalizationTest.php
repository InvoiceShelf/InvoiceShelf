<?php

use App\Models\Address;
use App\Models\Company;
use App\Models\Country;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

it('returns localized country name for German locale', function () {
    App::setLocale('de');

    $address = Address::factory()->create([
        'country_id' => Country::where('code', 'DE')->first()->id,
    ]);

    expect($address->country_name)->toBe('Deutschland');
});

it('returns localized country name for French locale', function () {
    App::setLocale('fr');

    $address = Address::factory()->create([
        'country_id' => Country::where('code', 'DE')->first()->id,
    ]);

    expect($address->country_name)->toBe('Allemagne');
});

it('returns null when address has no country', function () {
    $address = Address::factory()->create([
        'country_id' => null,
    ]);

    expect($address->country_name)->toBeNull();
});

it('falls back to database name when intl lookup fails', function () {
    App::setLocale('de');

    // Create a country with a code that won't be found in ICU data
    $country = new Country;
    $country->timestamps = false;
    $country->code = 'XX';
    $country->name = 'Unknown Land';
    $country->phonecode = 0;
    $country->save();

    $address = Address::factory()->create([
        'country_id' => $country->id,
    ]);

    expect($address->country_name)->toBe('Unknown Land');
});
