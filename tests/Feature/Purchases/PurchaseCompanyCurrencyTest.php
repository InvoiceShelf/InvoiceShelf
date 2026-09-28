<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Supplier;

beforeEach(fn () => purchaseFixtures($this));

test('purchasing records lock the company currency like sales records do', function () {
    $this->getJson('/api/v1/company/has-transactions')->assertOk()->assertJsonPath('has_transactions', true);

    $euro = (int) Currency::query()->where('code', 'EUR')->value('id');
    $this->postJson('/api/v1/company/settings', ['settings' => ['currency' => $euro]])
        ->assertOk()
        ->assertJsonPath('success', false);
    expect((int) CompanySetting::getSetting('currency', $this->companyId))->toBe($this->currencyId);

    Supplier::query()->forCompany($this->companyId)->delete();

    $this->getJson('/api/v1/company/has-transactions')->assertOk()->assertJsonPath('has_transactions', false);
});
