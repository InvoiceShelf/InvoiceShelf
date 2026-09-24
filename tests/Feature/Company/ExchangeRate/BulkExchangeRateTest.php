<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

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
