<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Domains\Sales\Models\Invoice;

beforeEach(fn () => purchaseFixtures($this));

test('deleting a member keeps the records they created and clears them as creator', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->companyId);

    $supplier = app(SupplierService::class)->save(null, $this->companyId, $member->id, [
        'name' => 'Member supplier',
        'currency_id' => $this->currencyId,
        'payment_terms' => 30,
    ]);
    $documents = app(PurchaseDocumentService::class);
    $bill = $documents->saveBill(null, $this->companyId, $member->id, [...purchaseBillPayload($this, 1000), 'supplier_id' => $supplier->id]);
    $credit = $documents->createCredit($this->companyId, $member->id, [...purchaseBillPayload($this, 100), 'supplier_id' => $supplier->id]);
    $settlements = app(SupplierSettlementService::class);
    $payment = $settlements->recordPayment($this->companyId, $member->id, [
        'supplier_id' => $supplier->id,
        'currency_id' => $this->currencyId,
        'exchange_rate' => 1,
        'amount' => 500,
        'payment_date' => '2026-09-02',
    ]);
    $refund = $settlements->recordRefund($this->companyId, $member->id, [
        'supplier_payment_id' => $payment->id,
        'amount' => 100,
        'exchange_rate' => 1,
        'payment_date' => '2026-09-03',
    ]);
    $expense = Expense::factory()->create(['company_id' => $this->companyId, 'creator_id' => $member->id]);
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId, 'creator_id' => $member->id]);

    $this->postJson('/api/v1/members/delete', ['users' => [$member->id]])->assertOk();

    $this->assertDatabaseMissing('users', ['id' => $member->id]);
    foreach ([
        [Supplier::class, $supplier->id],
        [Bill::class, $bill->id],
        [SupplierCredit::class, $credit->id],
        [SupplierPayment::class, $payment->id],
        [SupplierRefund::class, $refund->id],
        [Expense::class, $expense->id],
        [Invoice::class, $invoice->id],
    ] as [$model, $id]) {
        expect($model::query()->find($id))->not->toBeNull()
            ->creator_id->toBeNull();
    }
});
