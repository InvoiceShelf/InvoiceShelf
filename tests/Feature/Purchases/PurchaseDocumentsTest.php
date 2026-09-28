<?php

use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => purchaseFixtures($this));

test('bill totals are composed on the server using purchase tax snapshots', function () {
    $tax = TaxType::create(['name' => 'VAT 20%', 'company_id' => $this->companyId, 'type' => 'GENERAL', 'transaction_type' => 'purchases', 'percent' => 20, 'calculation_type' => 'percentage', 'compound_tax' => false]);
    $data = purchaseBillPayload($this, 10000);
    $data['items'][0]['tax_type_ids'] = [$tax->id];
    $data['total'] = 1;
    $response = $this->postJson('/api/v1/bills', $data)->assertSuccessful()->assertJsonPath('data.total', 12000)->assertJsonPath('data.tax', 2000);
    $id = $response->json('data.id');
    $this->getJson('/api/v1/bills/'.$id)->assertOk()->assertJsonPath('data.due_amount', 12000);
    $data['items'][0]['tax_type_ids'] = [$tax->id, $tax->id];
    $this->postJson('/api/v1/bills', $data)->assertUnprocessable();
});

test('credits on paid bills retain source prices and lock historical financial details', function () {
    $data = purchaseBillPayload($this, 101);
    $bill = $this->postJson('/api/v1/bills', $data)->assertSuccessful()->json('data');
    $this->postJson('/api/v1/supplier-payments', ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 101, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill['id'], 'amount' => 101]]])->assertSuccessful();
    $credit = [...$data, 'source_bill_id' => $bill['id']];
    $credit['items'][0] = [...$credit['items'][0], 'source_bill_item_id' => $bill['items'][0]['id'], 'price' => 999999, 'quantity' => 0.5];
    $this->postJson('/api/v1/supplier-credits', $credit)->assertSuccessful()->assertJsonPath('data.total', 50);
    $this->postJson('/api/v1/supplier-credits', $credit)->assertSuccessful()->assertJsonPath('data.total', 51);
    $this->postJson('/api/v1/supplier-credits', $credit)->assertUnprocessable();
    $data['items'][0]['price'] = 200;
    $this->putJson('/api/v1/bills/'.$bill['id'], $data)->assertUnprocessable();
    $data['items'][0]['price'] = 101;
    $data['notes'] = 'Payment confirmed';
    $this->putJson('/api/v1/bills/'.$bill['id'], $data)->assertSuccessful()->assertJsonPath('data.notes', 'Payment confirmed');
    expect(Bill::find($bill['id'])->total)->toBe(101)->and((int) SupplierCredit::sum('total'))->toBe(101);
});

test('bills and source attachments stay private and can be opened and voided', function () {
    Storage::fake(config('media-library.disk_name'));
    $data = [...purchaseBillPayload($this), 'status' => 'DRAFT'];
    $bill = $this->postJson('/api/v1/bills', $data)->assertSuccessful()->json('data');
    $this->postJson('/api/v1/bills/'.$bill['id'].'/actions', ['action' => 'open'])->assertSuccessful()->assertJsonPath('data.status', 'OPEN');
    $upload = $this->post('/api/v1/bills/'.$bill['id'].'/attachments', ['file' => UploadedFile::fake()->create('supplier.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
    $this->get('/api/v1/bills/'.$bill['id'].'/attachments/'.$upload->json('data.id'))->assertOk();
    $this->postJson('/api/v1/bills/'.$bill['id'].'/actions', ['action' => 'void'])->assertUnprocessable();
    $this->postJson('/api/v1/bills/'.$bill['id'].'/actions', ['action' => 'void', 'reason' => 'Duplicate supplier invoice'])->assertSuccessful()->assertJsonPath('data.status', 'VOID');
    $this->getJson('/api/v1/bills/'.$bill['id'])->assertOk()->assertJsonPath('data.void_reason', 'Duplicate supplier invoice');
});

test('a bill supplier can be corrected before settlement and is locked afterwards', function () {
    $other = Supplier::create(['company_id' => $this->companyId, 'name' => 'Correct supplier', 'currency_id' => $this->currencyId]);
    $data = purchaseBillPayload($this, 1000);
    $bill = $this->postJson('/api/v1/bills', $data)->assertSuccessful()->json('data');
    $data['supplier_id'] = $other->id;
    $this->putJson('/api/v1/bills/'.$bill['id'], $data)->assertSuccessful()->assertJsonPath('data.supplier_id', $other->id);
    $this->postJson('/api/v1/supplier-payments', ['supplier_id' => $other->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'amount' => 1000, 'payment_date' => '2026-09-02', 'allocations' => [['bill_id' => $bill['id'], 'amount' => 1000]]])->assertSuccessful();
    $data['supplier_id'] = $this->supplier->id;
    $this->putJson('/api/v1/bills/'.$bill['id'], $data)->assertUnprocessable();
});

test('the quantity left to credit has no float residue and can be credited as offered', function () {
    $data = purchaseBillPayload($this, 1000);
    $bill = $this->postJson('/api/v1/bills', $data)->assertSuccessful()->json('data');
    $line = $bill['items'][0]['id'];
    $credit = [...$data, 'source_bill_id' => $bill['id']];

    foreach ([0.7, 0.1] as $quantity) {
        $credit['items'][0] = [...$data['items'][0], 'source_bill_item_id' => $line, 'quantity' => $quantity];
        $this->postJson('/api/v1/supplier-credits', $credit)->assertSuccessful();
    }

    $left = $this->getJson('/api/v1/bills/'.$bill['id'])->assertOk()->json('data.creditable_quantities.'.$line);
    expect($left)->toBe(0.2);

    $credit['items'][0]['quantity'] = $left;
    $this->postJson('/api/v1/supplier-credits', $credit)->assertSuccessful();
    expect($this->getJson('/api/v1/bills/'.$bill['id'])->json('data.creditable_quantities.'.$line))->toEqual(0);
});

test('an older expense with no stored exchange rate can be credited and edited afterwards', function () {
    $expense = Expense::factory()->create([
        'company_id' => $this->companyId,
        'supplier_id' => $this->supplier->id,
        'expense_category_id' => $this->category->id,
        'currency_id' => $this->currencyId,
        'amount' => 1000,
        'base_amount' => 1000,
    ]);
    $expense->forceFill(['exchange_rate' => null])->saveQuietly();
    $credit = ['supplier_id' => $this->supplier->id, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'document_date' => '2026-09-05', 'source_expense_id' => $expense->id, 'source_amount' => 400, 'items' => purchaseBillPayload($this)['items']];

    $this->postJson('/api/v1/supplier-credits', $credit)->assertSuccessful()->assertJsonPath('data.exchange_rate', 1);

    $expense->refresh()->update(['exchange_rate' => 1, 'notes' => 'Receipt checked']);
    expect($expense->fresh()->notes)->toBe('Receipt checked')
        ->and(fn () => $expense->update(['amount' => 2000]))->toThrow(ValidationException::class);

    $foreign = Expense::factory()->create([
        'company_id' => $this->companyId,
        'supplier_id' => $this->supplier->id,
        'expense_category_id' => $this->category->id,
        'currency_id' => Currency::query()->where('code', 'EUR')->value('id'),
        'amount' => 1000,
        'base_amount' => 1100,
    ]);
    $foreign->forceFill(['exchange_rate' => null])->saveQuietly();

    $this->postJson('/api/v1/supplier-credits', [...$credit, 'currency_id' => $foreign->currency_id, 'source_expense_id' => $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_expense_id');
});
