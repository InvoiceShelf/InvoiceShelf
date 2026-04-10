<?php

use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('currencies endpoint returns common currencies first', function () {
    $response = getJson('/api/v1/currencies')->assertOk();

    $currencies = collect($response->json('data'));
    $commonCodes = Currency::COMMON_CURRENCY_CODES;
    $commonCount = count($commonCodes);

    $firstCodes = $currencies->take($commonCount)->pluck('code')->toArray();

    foreach ($commonCodes as $code) {
        expect($firstCodes)->toContain($code);
    }
});

test('common currencies follow the defined priority order', function () {
    $response = getJson('/api/v1/currencies')->assertOk();

    $currencies = collect($response->json('data'));
    $commonCodes = Currency::COMMON_CURRENCY_CODES;
    $commonCount = count($commonCodes);

    $firstCodes = $currencies->take($commonCount)->pluck('code')->toArray();

    expect($firstCodes)->toBe($commonCodes);
});

test('non-common currencies are sorted by name after common currencies', function () {
    $response = getJson('/api/v1/currencies')->assertOk();

    $currencies = collect($response->json('data'));
    $commonCodes = Currency::COMMON_CURRENCY_CODES;
    $commonCount = count($commonCodes);

    $restNames = $currencies->skip($commonCount)->pluck('name')->toArray();
    $sorted = $restNames;
    sort($sorted, SORT_STRING | SORT_FLAG_CASE);

    expect($restNames)->toBe($sorted);
});

test('all seeded currencies are returned', function () {
    $response = getJson('/api/v1/currencies')->assertOk();

    $currencies = collect($response->json('data'));

    expect($currencies->count())->toBe(Currency::count());
});
