<?php

use App\Domains\Accounts\Application\CompanyService;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::findOrFail(1);
    $this->companyId = $this->user->companies()->firstOrFail()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);
});

test('bulk exchange-rate setup still updates legacy documents through the domain contract', function () {
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->companyId);

    $currency = Currency::findOrFail(1);
    $invoice = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'currency_id' => $currency->id,
        'sub_total' => 100,
        'discount_val' => 8,
        'total' => 140,
        'tax' => 20,
        'due_amount' => 80,
        'exchange_rate' => null,
    ]);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [[
            'id' => $currency->id,
            'exchange_rate' => 2,
        ]],
    ])->assertOk()->assertJson(['success' => true]);

    $invoice->refresh();

    expect($invoice->exchange_rate)->toBe(2.0)
        ->and($invoice->base_discount_val)->toBe(16)
        ->and($invoice->base_sub_total)->toBe(200)
        ->and($invoice->base_total)->toBe(280)
        ->and($invoice->base_tax)->toBe(40)
        ->and($invoice->base_due_amount)->toBe(160)
        ->and(CompanySetting::getSetting('bulk_exchange_rate_configured', $this->companyId))
        ->toBe('YES');
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
    app(CompanyService::class)->setupDefaults($company);

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
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->companyId);
    $currency = Currency::findOrFail(1);
    $other = otherCompanyWithLegacyDocuments($currency);
    $own = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'currency_id' => $currency->id,
        'total' => 100,
        'due_amount' => 100,
        'exchange_rate' => null,
    ]);

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $currency->id, 'exchange_rate' => 2]],
    ])->assertOk()->assertJson(['success' => true]);

    expect($own->fresh()->exchange_rate)->toBe(2.0)
        ->and($own->fresh()->base_total)->toBe(200);

    foreach (['invoice', 'estimate', 'payment', 'tax'] as $kind) {
        expect($other[$kind]->fresh()->exchange_rate)->toBeNull();
    }

    expect($other['invoice']->fresh()->base_total)->toBe(100)
        ->and($other['invoice']->fresh()->base_due_amount)->toBe(100)
        ->and($other['estimate']->fresh()->base_total)->toBe(100)
        ->and($other['payment']->fresh()->base_amount)->toBe(50)
        ->and($other['tax']->fresh()->base_amount)->toBe(10)
        ->and(CompanySetting::getSetting('bulk_exchange_rate_configured', $other['company']->id))->toBe('YES');
});

test('the backfill leaves documents that already have an exchange rate alone', function () {
    CompanySetting::setSettings(['bulk_exchange_rate_configured' => 'NO'], $this->companyId);
    $currency = Currency::findOrFail(1);
    $issued = Invoice::factory()->create([
        'company_id' => $this->companyId,
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

    expect($issued->fresh()->exchange_rate)->toBe(1.5)
        ->and($issued->fresh()->base_total)->toBe(150)
        ->and($issued->fresh()->base_due_amount)->toBe(150);
});

test('the backfill lists only the active company\'s currencies', function () {
    $own = Currency::findOrFail(1);
    $foreign = Currency::query()->whereKeyNot($own->id)->firstOrFail();
    otherCompanyWithLegacyDocuments($foreign);
    Invoice::factory()->create([
        'company_id' => $this->companyId,
        'currency_id' => $own->id,
        'exchange_rate' => null,
    ]);

    $ids = collect(getJson('/api/v1/currencies/used')->assertOk()->json('currencies'))->pluck('id');

    expect($ids->all())->toContain($own->id)
        ->and($ids->all())->not->toContain($foreign->id);
});

test('company settings never reopen the backfill', function () {
    $currency = Currency::findOrFail(1);
    $other = otherCompanyWithLegacyDocuments($currency);

    postJson('/api/v1/company/settings', [
        'settings' => ['bulk_exchange_rate_configured' => 'NO', 'invoice_use_time' => 'YES'],
    ])->assertOk()->assertJson(['success' => true]);

    expect(CompanySetting::getSetting('bulk_exchange_rate_configured', $this->companyId))->toBe('YES')
        ->and(CompanySetting::getSetting('invoice_use_time', $this->companyId))->toBe('YES');

    postJson('/api/v1/currencies/bulk-update-exchange-rate', [
        'currencies' => [['id' => $currency->id, 'exchange_rate' => 2]],
    ])->assertOk()->assertExactJson(['error' => false]);

    expect($other['invoice']->fresh()->exchange_rate)->toBeNull();
});
