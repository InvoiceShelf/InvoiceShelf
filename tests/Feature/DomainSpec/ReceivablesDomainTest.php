<?php

// Domain behavioural suite — Receivables (spec: receivables-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);

    $this->usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $this->customerId = postJson('/api/v1/customers', ['name' => 'Payer', 'currency_id' => $this->usd])
        ->json('data.id');

    $this->makeInvoice = function (string $number, int $total, bool $sent = true, ?int $customerId = null) {
        $id = postJson('/api/v1/invoices', [
            'invoice_date' => '2026-03-01', 'customer_id' => $customerId ?? $this->customerId,
            'invoice_number' => $number, 'discount' => 0, 'discount_val' => 0,
            'sub_total' => $total, 'total' => $total, 'tax' => 0, 'template_name' => 'invoice1',
            'exchange_rate' => 3, 'currency_id' => $this->usd,
            'items' => [['name' => 'Line', 'quantity' => 1, 'price' => $total, 'description' => '',
                'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => $total]],
        ])->assertSuccessful()->json('data.id');
        if ($sent) {
            postJson("/api/v1/invoices/{$id}/status", ['status' => 'SENT'])->assertOk();
        }

        return $id;
    };
});

it('walks the allocation guard ladder', function () {
    $inv = ($this->makeInvoice)('INV-G-1', 100);
    $draft = ($this->makeInvoice)('INV-G-2', 100, sent: false);

    $base = ['payment_date' => '2026-03-02', 'customer_id' => $this->customerId,
        'amount' => 100, 'exchange_rate' => 3];

    // Duplicates are already refused at the request layer (per-row distinct rule).
    postJson('/api/v1/payments', $base + ['payment_number' => 'PAY-G-1',
        'allocations' => [['invoice_id' => $inv, 'amount' => 50], ['invoice_id' => $inv, 'amount' => 50]],
    ])->assertStatus(422)->assertJsonValidationErrors(['allocations.0.invoice_id']);

    postJson('/api/v1/payments', $base + ['payment_number' => 'PAY-G-2',
        'allocations' => [['invoice_id' => $inv, 'amount' => 150]],
    ])->assertStatus(422)->assertJsonPath('errors.allocations.0', 'payment_allocation_exceeds_payment_amount');

    postJson('/api/v1/payments', $base + ['payment_number' => 'PAY-G-3',
        'allocations' => [['invoice_id' => $draft, 'amount' => 50]],
    ])->assertStatus(422)->assertJsonPath('errors.allocations.0', 'payment_allocation_invoice_not_payable');

    postJson('/api/v1/payments', $base + ['payment_number' => 'PAY-G-4',
        'allocations' => [['invoice_id' => $inv, 'amount' => 100]],
    ])->assertSuccessful();

    postJson('/api/v1/payments', $base + ['payment_number' => 'PAY-G-5',
        'allocations' => [['invoice_id' => $inv, 'amount' => 1]],
    ])->assertStatus(422)->assertJsonPath('errors.allocations.0', 'payment_allocation_exceeds_invoice_balance');
});

it('settles the invoice on full allocation and restores it when the payment is deleted', function () {
    $inv = ($this->makeInvoice)('INV-S-1', 100);
    $payment = postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-S-1',
        'allocations' => [['invoice_id' => $inv, 'amount' => 100]],
    ])->assertSuccessful()->json('data');

    $row = DB::table('invoices')->where('id', $inv)->first();
    expect((int) $row->due_amount)->toBe(0);
    expect($row->status)->toBe('COMPLETED');
    expect($row->paid_status)->toBe('PAID');

    postJson('/api/v1/payments/delete', ['ids' => [$payment['id']]])->assertOk();
    $row = DB::table('invoices')->where('id', $inv)->first();
    expect((int) $row->due_amount)->toBe(100);
    expect($row->paid_status)->toBe('UNPAID');
    expect($row->status)->toBe('SENT');
});

it('prorates base amounts with the last-row remainder rule', function () {
    $a = ($this->makeInvoice)('INV-R-1', 33);
    $b = ($this->makeInvoice)('INV-R-2', 33);
    $c = ($this->makeInvoice)('INV-R-3', 34);

    $id = postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-R-1',
        'allocations' => [
            ['invoice_id' => $a, 'amount' => 33],
            ['invoice_id' => $b, 'amount' => 33],
            ['invoice_id' => $c, 'amount' => 34],
        ],
    ])->assertSuccessful()->json('data.id');

    $bases = DB::table('customer_payment_allocations')->where('payment_id', $id)
        ->orderBy('invoice_id')->pluck('base_amount')->map(fn ($v) => (int) $v)->all();
    expect(array_sum($bases))->toBe(300);
    expect($bases)->toBe([99, 99, 102]);
});

it('allows reshaping a payment’s own allocations across covered invoices', function () {
    $a = ($this->makeInvoice)('INV-M-1', 100);
    $b = ($this->makeInvoice)('INV-M-2', 100);
    $id = postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-M-1',
        'allocations' => [['invoice_id' => $a, 'amount' => 100]],
    ])->json('data.id');

    putJson("/api/v1/payments/{$id}/allocations", [
        'allocations' => [['invoice_id' => $b, 'amount' => 100]],
    ])->assertSuccessful();

    expect((int) DB::table('invoices')->where('id', $a)->value('due_amount'))->toBe(100);
    expect((int) DB::table('invoices')->where('id', $b)->value('due_amount'))->toBe(0);
});

it('locks the payment customer while allocated and frees it after deallocation', function () {
    $inv = ($this->makeInvoice)('INV-L-1', 100);
    $id = postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-L-1',
        'allocations' => [['invoice_id' => $inv, 'amount' => 100]],
    ])->json('data.id');

    $other = postJson('/api/v1/customers', ['name' => 'Other Payer', 'currency_id' => $this->usd])
        ->json('data.id');

    putJson("/api/v1/payments/{$id}", [
        'payment_date' => '2026-03-02', 'customer_id' => $other, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-L-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['customer_id']);

    putJson("/api/v1/payments/{$id}/allocations", ['allocations' => []])->assertSuccessful();
    putJson("/api/v1/payments/{$id}", [
        'payment_date' => '2026-03-02', 'customer_id' => $other, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-L-1',
    ])->assertSuccessful();
});

it('applies customer credit on top of existing allocations', function () {
    $a = ($this->makeInvoice)('INV-C-1', 100);
    $b = ($this->makeInvoice)('INV-C-2', 100);
    $paymentId = postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-C-1',
        'allocations' => [['invoice_id' => $a, 'amount' => 40]],
    ])->json('data.id');

    postJson("/api/v1/customers/{$this->customerId}/credit-allocations", [
        'allocations' => [['payment_id' => $paymentId, 'invoice_id' => $b, 'amount' => 30]],
    ])->assertOk()->assertJson(['success' => true]);

    $rows = DB::table('customer_payment_allocations')->where('payment_id', $paymentId)
        ->orderBy('invoice_id')->get(['invoice_id', 'amount']);
    expect($rows->pluck('amount')->map(fn ($v) => (int) $v)->all())->toBe([40, 30]);
    expect((int) DB::table('invoices')->where('id', $b)->value('due_amount'))->toBe(70);
});

it('rejects the legacy direct invoice field on payments', function () {
    $inv = ($this->makeInvoice)('INV-P-1', 100);
    postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 3, 'payment_number' => 'PAY-P-1', 'invoice_id' => $inv,
    ])->assertStatus(422)->assertJsonValidationErrors(['invoice_id']);
});

it('refuses to delete payment methods referenced by payments or expenses', function () {
    $method = postJson('/api/v1/payment-methods', ['name' => 'Wire'])->assertSuccessful()->json('data');
    postJson('/api/v1/payments', [
        'payment_date' => '2026-03-02', 'customer_id' => $this->customerId, 'amount' => 10,
        'exchange_rate' => 3, 'payment_number' => 'PAY-W-1', 'payment_method_id' => $method['id'],
    ])->assertSuccessful();
    deleteJson("/api/v1/payment-methods/{$method['id']}")
        ->assertStatus(422)->assertJson(['error' => 'payments_attached']);

    $method2 = postJson('/api/v1/payment-methods', ['name' => 'Petty cash'])->json('data');
    $catId = postJson('/api/v1/categories', ['name' => 'Misc'])->json('data.id');
    $companyCurrency = DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'currency')->value('value');
    postJson('/api/v1/expenses', ['expense_date' => '2026-03-03', 'expense_category_id' => $catId,
        'amount' => 5, 'currency_id' => $companyCurrency, 'payment_method_id' => $method2['id']])->assertSuccessful();
    deleteJson("/api/v1/payment-methods/{$method2['id']}")
        ->assertStatus(422)->assertJson(['error' => 'expenses_attached']);
});
