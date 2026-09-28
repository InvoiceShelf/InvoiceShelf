<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(fn () => purchaseFixtures($this));

test('purchase records and write references are scoped to the active company', function () {
    $other = Company::factory()->create();
    $supplier = Supplier::create(['company_id' => $other->id, 'name' => 'Private supplier', 'currency_id' => $this->currencyId, 'payment_terms' => 30]);
    $this->getJson('/api/v1/suppliers')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/suppliers/'.$supplier->id)->assertNotFound();
    $this->postJson('/api/v1/bills', [...purchaseBillPayload($this), 'supplier_id' => $supplier->id])->assertUnprocessable();
});

test('purchase permissions distinguish viewing from recording money', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $user->companies()->attach($this->companyId);
    BouncerFacade::scope()->to($this->companyId);
    BouncerFacade::allow($user)->to('view-supplier', Supplier::class);
    BouncerFacade::refresh();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/suppliers')->assertOk();
    $this->postJson('/api/v1/suppliers', ['name' => 'New supplier', 'currency_id' => $this->currencyId, 'payment_terms' => 30])->assertForbidden();
    $this->getJson('/api/v1/supplier-payments')->assertForbidden();
});

test('purchase form options serialize only reference fields', function () {
    $this->getJson('/api/v1/purchase-options')->assertOk()->assertJsonPath('data.categories.0.name', 'Hosting');
});
