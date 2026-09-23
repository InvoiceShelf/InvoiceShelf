<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Application\ExchangeRateLookup;
use App\Domains\Money\Models\Currency;
use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Money\Models\ExchangeRateProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);

    $this->usd = Currency::where('code', 'USD')->first();
    $this->eur = Currency::where('code', 'EUR')->first();
    CompanySetting::setSettings(['currency' => $this->usd->id], $this->companyId);
});

function openExchangeRateProvider(array $currencies, bool $active = true): ExchangeRateProvider
{
    return ExchangeRateProvider::factory()->create([
        'driver' => 'open_exchange_rate',
        'key' => 'test-key',
        'currencies' => $currencies,
        'active' => $active,
        'driver_config' => [],
    ]);
}

test('an active provider covering the currency answers with a live quote', function () {
    openExchangeRateProvider(['EUR']);
    Http::fake(['*' => Http::response(['rates' => ['USD' => 1.0843]])]);

    getJson("api/v1/currencies/{$this->eur->id}/exchange-rate")
        ->assertOk()
        ->assertExactJson(['exchangeRate' => [1.0843]]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'base=EUR&symbols=USD'));
    expect(app(ExchangeRateLookup::class)->rate($this->eur, $this->companyId))->toBe(1.0843);
});

test('without a live quote the last logged rate for the pair answers', function () {
    ExchangeRateLog::query()->create([
        'company_id' => $this->companyId,
        'base_currency_id' => $this->eur->id,
        'currency_id' => $this->usd->id,
        'exchange_rate' => 1.05,
    ]);
    $this->travel(1)->minutes();
    ExchangeRateLog::query()->create([
        'company_id' => $this->companyId,
        'base_currency_id' => $this->eur->id,
        'currency_id' => $this->usd->id,
        'exchange_rate' => 1.07,
    ]);

    getJson("api/v1/currencies/{$this->eur->id}/exchange-rate")
        ->assertOk()
        ->assertJsonPath('exchangeRate.0', fn ($rate) => (float) $rate === 1.07);

    expect(app(ExchangeRateLookup::class)->rate($this->eur, $this->companyId))->toBe(1.07);
});

test('a provider that fails falls back to the log', function () {
    openExchangeRateProvider(['EUR']);
    Http::fake(['*' => Http::response(['error' => true, 'description' => 'Invalid App ID', 'message' => 'invalid_app_id'], 401)]);
    ExchangeRateLog::query()->create([
        'company_id' => $this->companyId,
        'base_currency_id' => $this->eur->id,
        'currency_id' => $this->usd->id,
        'exchange_rate' => 1.02,
    ]);

    getJson("api/v1/currencies/{$this->eur->id}/exchange-rate")
        ->assertOk()
        ->assertJsonPath('exchangeRate.0', fn ($rate) => (float) $rate === 1.02);
});

test('an inactive provider is not asked', function () {
    openExchangeRateProvider(['EUR'], active: false);
    Http::fake();

    getJson("api/v1/currencies/{$this->eur->id}/exchange-rate")
        ->assertOk()
        ->assertExactJson(['error' => 'no_exchange_rate_available']);

    Http::assertNothingSent();
    expect(app(ExchangeRateLookup::class)->rate($this->eur, $this->companyId))->toBeNull();
});
