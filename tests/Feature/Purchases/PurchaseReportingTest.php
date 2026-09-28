<?php

use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Reporting\Queries\PurchasesQuery;
use App\Domains\Taxation\Models\TaxType;

beforeEach(fn () => purchaseFixtures($this));

test('purchase costs cash and outstanding liabilities have independent dates and totals', function () {
    $documents = app(PurchaseDocumentService::class);
    $settlements = app(SupplierSettlementService::class);
    $query = app(PurchasesQuery::class);
    $bill = $documents->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 10000));
    $first = $query->report($this->companyId, '2026-09-01', '2026-09-30');
    expect($first['purchases']['gross'])->toBe(10000)->and($first['cash']['net_cash_out'])->toBe(0)->and($first['payables']['outstanding'])->toBe(10000);
    $payment = $settlements->recordPayment($this->companyId, $this->user->id, ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 12000, 'payment_date' => '2026-10-01', 'allocations' => [['bill_id' => $bill->id, 'amount' => 10000]]]);
    $settlements->recordRefund($this->companyId, $this->user->id, ['supplier_payment_id' => $payment->id, 'amount' => 1000, 'exchange_rate' => 1, 'payment_date' => '2026-10-02']);
    $october = $query->report($this->companyId, '2026-10-01', '2026-10-31');
    expect($october['purchases']['gross'])->toBe(0)->and($october['cash']['net_cash_out'])->toBe(11000)->and($october['payables']['available_advances'])->toBe(1000)->and($october['payables']['outstanding'])->toBe(0);
    $this->getJson('/api/v1/reports/purchases?from_date=2026-09-01&to_date=2026-10-31')->assertOk()->assertJsonPath('data.purchases.gross', 10000)->assertJsonPath('data.cash.net_cash_out', 11000);
});

test('foreign purchase tax rounding cannot exceed the rounded document value', function () {
    $foreign = Currency::where('code', 'EUR')->firstOrFail();
    $taxIds = [];
    foreach (['First fixed tax', 'Second fixed tax'] as $name) {
        $taxIds[] = TaxType::create(['name' => $name, 'company_id' => $this->companyId, 'type' => 'GENERAL', 'transaction_type' => 'purchases', 'calculation_type' => 'fixed', 'fixed_amount' => 1000, 'compound_tax' => false])->id;
    }
    $data = purchaseBillPayload($this, 100);
    $data['currency_id'] = $foreign->id;
    $data['exchange_rate'] = 0.0005;
    $data['items'][0]['tax_type_ids'] = $taxIds;
    app(PurchaseDocumentService::class)->saveBill(null, $this->companyId, $this->user->id, $data);
    $report = app(PurchasesQuery::class)->report($this->companyId, '2026-09-01', '2026-09-30');
    expect($report['purchases'])->toBe(['gross' => 1, 'tax' => 1, 'net' => 0]);
});
