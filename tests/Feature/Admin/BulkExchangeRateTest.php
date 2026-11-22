<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->company = User::where('role', 'super admin')->first()->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
});

test('only the owner may run the exchange-rate backfill', function () {
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->company->id);

    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    Sanctum::actingAs($member, ['*']);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => 1, 'exchange_rate' => 2]],
    ])->assertForbidden();

    expect(CompanySetting::getSetting('bulk_exchange_rate_configured', $this->company->id))->toBe('NO');
});

/**
 * Another company on the same installation, owned by someone else, with one
 * document of each kind the backfill touches, all in `$currency` and none
 * with an exchange rate yet.
 *
 * @return array{company: Company, invoice: Invoice, estimate: Estimate, payment: Payment, tax: Tax}
 */
function otherCompanyWithLegacyDocuments(Currency $currency): array
{
    $company = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    $company->setupDefaultData();

    $common = ['company_id' => $company->id, 'currency_id' => $currency->id, 'exchange_rate' => null];

    return [
        'company' => $company,
        'invoice' => Invoice::factory()->create([...$common, 'total' => 100, 'due_amount' => 100, 'base_total' => 100, 'base_due_amount' => 100]),
        'estimate' => Estimate::factory()->create([...$common, 'total' => 100, 'base_total' => 100]),
        'payment' => Payment::factory()->create([...$common, 'amount' => 50, 'base_amount' => 50]),
        'tax' => Tax::factory()->create([...$common, 'amount' => 10, 'base_amount' => 10]),
    ];
}

test('the backfill leaves other companies\' documents alone', function () {
    Sanctum::actingAs(User::find(1), ['*']);
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->company->id);
    $currency = Currency::findOrFail(1);
    $other = otherCompanyWithLegacyDocuments($currency);
    $own = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'currency_id' => $currency->id,
        'total' => 100,
        'due_amount' => 100,
        'exchange_rate' => null,
    ]);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $currency->id, 'exchange_rate' => 2]],
    ])->assertOk()->assertJson(['success' => true]);

    expect((float) $own->fresh()->exchange_rate)->toBe(2.0)
        ->and((int) $own->fresh()->base_total)->toBe(200);

    foreach (['invoice', 'estimate', 'payment', 'tax'] as $kind) {
        expect($other[$kind]->fresh()->exchange_rate)->toBeNull();
    }

    expect((int) $other['invoice']->fresh()->base_total)->toBe(100)
        ->and((int) $other['invoice']->fresh()->base_due_amount)->toBe(100)
        ->and((int) $other['estimate']->fresh()->base_total)->toBe(100)
        ->and((int) $other['payment']->fresh()->base_amount)->toBe(50)
        ->and((int) $other['tax']->fresh()->base_amount)->toBe(10)
        ->and(CompanySetting::getSetting('bulk_exchange_rate_configured', $other['company']->id))->toBe('YES');
});

test('the backfill leaves documents that already have an exchange rate alone', function () {
    Sanctum::actingAs(User::find(1), ['*']);
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->company->id);
    $currency = Currency::findOrFail(1);
    $issued = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'currency_id' => $currency->id,
        'total' => 100,
        'due_amount' => 100,
        'exchange_rate' => 1.5,
        'base_total' => 150,
        'base_due_amount' => 150,
    ]);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $currency->id, 'exchange_rate' => 2]],
    ])->assertOk()->assertJson(['success' => true]);

    expect((float) $issued->fresh()->exchange_rate)->toBe(1.5)
        ->and((int) $issued->fresh()->base_total)->toBe(150)
        ->and((int) $issued->fresh()->base_due_amount)->toBe(150);
});

test('the backfill lists only the active company\'s currencies', function () {
    Sanctum::actingAs(User::find(1), ['*']);
    $own = Currency::findOrFail(1);
    $foreign = Currency::query()->whereKeyNot($own->id)->firstOrFail();
    otherCompanyWithLegacyDocuments($foreign);
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'currency_id' => $own->id,
        'exchange_rate' => null,
    ]);

    $ids = collect(getJson('/api/v1/currencies/used')->assertOk()->json('currencies'))->pluck('id');

    expect($ids->all())->toContain($own->id)
        ->and($ids->all())->not->toContain($foreign->id);
});

test('company settings never reopen the backfill', function () {
    Sanctum::actingAs(User::find(1), ['*']);
    $currency = Currency::findOrFail(1);
    $other = otherCompanyWithLegacyDocuments($currency);
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'YES'], $this->company->id);

    postJson('/api/v1/company/settings', [
        'settings' => ['bulk_exchange_rate_configured' => 'NO', 'invoice_use_time' => 'YES'],
    ])->assertOk()->assertJson(['success' => true]);

    expect(CompanySetting::getSetting('bulk_exchange_rate_configured', $this->company->id))->toBe('YES')
        ->and(CompanySetting::getSetting('invoice_use_time', $this->company->id))->toBe('YES');

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $currency->id, 'exchange_rate' => 2]],
    ])->assertOk()->assertExactJson(['error' => false]);

    expect($other['invoice']->fresh()->exchange_rate)->toBeNull();
});
