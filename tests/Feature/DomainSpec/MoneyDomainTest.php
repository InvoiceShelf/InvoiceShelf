<?php

// Domain behavioural suite — Money (spec: money-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

it('lists currencies common-first, then the rest by name', function () {
    $codes = collect(getJson('/api/v1/currencies')->assertOk()->json('data'))->pluck('code');

    expect($codes->take(10)->values()->all())
        ->toBe(['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR', 'BRL']);

    // Case-insensitively: "CFP Franc" belongs beside "Central African Franc",
    // not at the head of the C block where a byte comparison puts it.
    $restNames = collect(getJson('/api/v1/currencies')->json('data'))->skip(10)->pluck('name')->values();
    expect($restNames->all())
        ->toBe($restNames->sortBy(fn (string $name): string => mb_strtolower($name))->values()->all());
});

it('creates a provider after live validation and enforces the one-active-provider-per-currency rule', function () {
    Http::fake(['api.currencyfreaks.com/*' => Http::response(['rates' => ['INR' => '83.1']])]);

    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_freak', 'key' => 'k1', 'currencies' => ['USD'], 'active' => true,
    ])->assertSuccessful();

    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_freak', 'key' => 'k2', 'currencies' => ['USD'], 'active' => true,
    ])->assertStatus(422)->assertJson(['error' => 'currency_used']);
});

it('maps an invalid provider key to the invalid-key error', function () {
    Http::fake(['api.currencyfreaks.com/*' => Http::response([
        'success' => false, 'error' => ['status' => 404, 'message' => 'bad key'],
    ])]);

    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_freak', 'key' => 'bad', 'currencies' => ['USD'], 'active' => true,
    ])->assertStatus(422)->assertJson(['error' => 'invalid_key']);
});

it('refuses to delete an active provider and deletes an inactive one', function () {
    Http::fake(['api.currencyfreaks.com/*' => Http::response(['rates' => ['INR' => '83.1']])]);
    $id = postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_freak', 'key' => 'k', 'currencies' => ['USD'], 'active' => true,
    ])->json('data.id');

    deleteJson("/api/v1/exchange-rate-providers/{$id}")
        ->assertStatus(422)->assertJson(['error' => 'provider_active']);

    DB::table('exchange_rate_providers')->where('id', $id)->update(['active' => false]);
    deleteJson("/api/v1/exchange-rate-providers/{$id}")->assertOk()->assertJson(['success' => true]);
});

it('resolves rates live first, then from the log, then reports none', function () {
    $usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $base = DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'currency')->value('value');

    getJson("/api/v1/currencies/{$usd}/exchange-rate")
        ->assertOk()->assertJson(['error' => 'no_exchange_rate_available']);

    DB::table('exchange_rate_logs')->insert([
        'exchange_rate' => 82.5, 'base_currency_id' => $usd, 'currency_id' => $base,
        'company_id' => $this->companyId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    getJson("/api/v1/currencies/{$usd}/exchange-rate")
        ->assertOk()->assertJsonPath('exchangeRate.0', 82.5);

    Http::fake(['api.currencyfreaks.com/*' => Http::response(['rates' => ['INR' => '99.9']])]);
    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_freak', 'key' => 'k', 'currencies' => ['USD'], 'active' => true,
    ])->assertSuccessful();
    getJson("/api/v1/currencies/{$usd}/exchange-rate")
        ->assertOk()->assertJsonPath('exchangeRate.0', '99.9');
});

it('gates the historical backfill and reproduces its defective arithmetic', function () {
    $usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $customerId = postJson('/api/v1/customers', ['name' => 'Backfill Co', 'currency_id' => $usd])
        ->assertSuccessful()->json('data.id');

    $invoice = postJson('/api/v1/invoices', [
        'invoice_date' => '2026-01-10', 'customer_id' => $customerId,
        'invoice_number' => 'INV-000001', 'discount' => 0, 'discount_val' => 0,
        'sub_total' => 1000, 'total' => 1000, 'tax' => 0, 'template_name' => 'invoice1',
        'exchange_rate' => 3, 'currency_id' => $usd,
        'items' => [['name' => 'Thing', 'quantity' => 1, 'price' => 1000, 'description' => '',
            'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1000]],
    ])->assertSuccessful()->json('data');

    // The gate: any value other than NO means nothing happens.
    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $usd, 'exchange_rate' => 2]],
    ])->assertOk()->assertExactJson(['error' => false]);

    DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'bulk_exchange_rate_configured')->update(['value' => 'NO']);
    DB::table('invoices')->where('id', $invoice['id'])->update(['exchange_rate' => null]);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $usd, 'exchange_rate' => 2]],
    ])->assertOk()->assertJson(['success' => true]);

    $row = DB::table('invoices')->where('id', $invoice['id'])->first();
    expect((float) $row->exchange_rate)->toBe(2.0);
    expect((int) $row->base_sub_total)->toBe(2000);
    expect((int) $row->base_total)->toBe(2000);
    // Defect, kept deliberately: base discount sourced from the sub-total.
    expect((int) $row->base_discount_val)->toBe(2000);

    expect(DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'bulk_exchange_rate_configured')->value('value'))->toBe('YES');
});

it('lets only the owner run the exchange-rate backfill', function () {
    DB::table('company_settings')->updateOrInsert(
        ['company_id' => $this->companyId, 'option' => 'bulk_exchange_rate_configured'],
        ['value' => 'NO'],
    );
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->companyId);
    Sanctum::actingAs($member, ['*']);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => 1, 'exchange_rate' => 2]],
    ])->assertForbidden();

    expect(DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'bulk_exchange_rate_configured')->value('value'))->toBe('NO');
});
