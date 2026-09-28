<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;

beforeEach(fn () => purchaseFixtures($this));

test('each company numbers its purchasing documents from one', function () {
    $documents = app(PurchaseDocumentService::class);
    $settlements = app(SupplierSettlementService::class);

    $other = Company::factory()->create();
    CompanySetting::setSettings(['currency' => $this->currencyId], $other->id);
    $otherSupplier = Supplier::create(['company_id' => $other->id, 'name' => 'Other supplier', 'currency_id' => $this->currencyId]);
    $otherCategory = ExpenseCategory::create(['company_id' => $other->id, 'name' => 'Rent']);
    $otherPayload = purchaseBillPayload($this, 1000);
    $otherPayload['supplier_id'] = $otherSupplier->id;
    $otherPayload['items'][0]['expense_category_id'] = $otherCategory->id;
    $documents->saveBill(null, $other->id, null, $otherPayload);
    $documents->saveBill(null, $other->id, null, $otherPayload);

    $first = $documents->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 1000));
    $second = $documents->saveBill(null, $this->companyId, $this->user->id, [...purchaseBillPayload($this, 1000), 'status' => 'DRAFT']);
    $credit = $documents->createCredit($this->companyId, $this->user->id, purchaseBillPayload($this, 100));
    $payment = $settlements->recordPayment($this->companyId, $this->user->id, [
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currencyId,
        'exchange_rate' => 1,
        'amount' => 500,
        'payment_date' => '2026-09-02',
    ]);
    $refund = $settlements->recordRefund($this->companyId, $this->user->id, [
        'supplier_payment_id' => $payment->id,
        'amount' => 100,
        'exchange_rate' => 1,
        'payment_date' => '2026-09-03',
    ]);

    expect([$first->number, $second->number, $credit->number, $payment->number, $refund->number])
        ->toBe(['BILL-000001', 'BILL-000002', 'SC-000001', 'SP-000001', 'SR-000001'])
        ->and($second->sequence_number)->toBe(2);

    $documents->saveBill($first, $this->companyId, $this->user->id, [...purchaseBillPayload($this, 2000), 'reference' => 'Edited']);

    expect($first->fresh()->number)->toBe('BILL-000001');
});

test('a company number format is used for its bills', function () {
    CompanySetting::setSettings(['bill_number_format' => '{{SERIES:AP}}{{DELIMITER:/}}{{SEQUENCE:4}}'], $this->companyId);

    $bill = app(PurchaseDocumentService::class)->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 1000));

    expect($bill->number)->toBe('AP/0001');
});
