<?php

use App\Domains\Purchases\Application\PurchaseDocumentService;
use App\Domains\Purchases\Application\SupplierSettlementService;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => purchaseFixtures($this));

test('payments advances credits and refunds reconcile without changing the original purchase', function () {
    $documents = app(PurchaseDocumentService::class);
    $settlements = app(SupplierSettlementService::class);
    $bill = $documents->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this));
    $payment = $settlements->recordPayment($this->companyId, $this->user->id, [
        'supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1,
        'amount' => 120000, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill->id, 'amount' => 100000]],
    ]);
    expect($bill->fresh()->due_amount)->toBe(0)->and($payment->fresh()->available_amount)->toBe(20000);
    $credit = $documents->createCredit($this->companyId, $this->user->id, array_replace_recursive(purchaseBillPayload($this), [
        'source_bill_id' => $bill->id, 'items' => [['source_bill_item_id' => $bill->items->first()->id, 'quantity' => 0.15]],
    ]));
    expect($credit->total)->toBe(15000);
    $refund = $settlements->recordRefund($this->companyId, $this->user->id, ['supplier_credit_id' => $credit->id, 'amount' => 5000, 'exchange_rate' => 1, 'payment_date' => '2026-09-03']);
    $second = $documents->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 30000));
    $settlements->replaceAllocations($credit, [['bill_id' => $second->id, 'amount' => 10000]]);
    $settlements->replaceAllocations($payment, [['bill_id' => $bill->id, 'amount' => 100000], ['bill_id' => $second->id, 'amount' => 20000]]);
    expect($second->fresh()->due_amount)->toBe(0)
        ->and($payment->fresh()->available_amount)->toBe(0)
        ->and($credit->fresh()->available_amount)->toBe(0)
        ->and($payment->amount - $refund->amount)->toBe(115000)
        ->and($bill->total + $second->total - $credit->total)->toBe(115000);
});

test('overpayment of a bill is rejected atomically and refunds cannot consume allocated funds', function () {
    $bill = app(PurchaseDocumentService::class)->saveBill(null, $this->companyId, $this->user->id, purchaseBillPayload($this, 100));
    $service = app(SupplierSettlementService::class);
    $input = ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 101, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill->id, 'amount' => 101]]];
    expect(fn () => $service->recordPayment($this->companyId, $this->user->id, $input))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('supplier_payments', 0);
    $input['allocations'][0]['amount'] = 100;
    $payment = $service->recordPayment($this->companyId, $this->user->id, $input);
    expect(fn () => $service->recordRefund($this->companyId, $this->user->id, ['supplier_payment_id' => $payment->id, 'amount' => 2, 'exchange_rate' => 1, 'payment_date' => '2026-09-02']))->toThrow(ValidationException::class);
    $service->replaceAllocations($payment, []);
    expect($bill->fresh()->due_amount)->toBe(100);
    $refund = $service->recordRefund($this->companyId, $this->user->id, ['supplier_payment_id' => $payment->id, 'amount' => 101, 'exchange_rate' => 1, 'payment_date' => '2026-09-02']);
    expect($payment->fresh()->available_amount)->toBe(0);
    $service->void($refund, 'Incorrect entry');
    expect($payment->fresh()->available_amount)->toBe(101);
});
