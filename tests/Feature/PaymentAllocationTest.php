<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Application\PaymentAllocationService;
use App\Domains\Receivables\Jobs\GeneratePaymentPdfJob;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);
});

function allocatableInvoice(int $amount = 10000): Invoice
{
    return Invoice::factory()->create([
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => $amount,
        'total' => $amount,
        'due_amount' => $amount,
        'exchange_rate' => 1,
        'base_sub_total' => $amount,
        'base_total' => $amount,
        'base_due_amount' => $amount,
    ]);
}

function allocationPayment(Invoice $invoice, int $amount): Payment
{
    return Payment::factory()->create([
        ...$invoice->only(['company_id', 'customer_id', 'currency_id']),
        'amount' => $amount,
        'base_amount' => $amount,
        'exchange_rate' => 1,
    ]);
}

test('a payment can be allocated across multiple invoices and retain credit', function () {
    $first = allocatableInvoice(300);
    $second = allocatableInvoice(400);
    $third = allocatableInvoice(200);

    foreach ([$second, $third] as $invoice) {
        $invoice->update([
            'company_id' => $first->company_id,
            'customer_id' => $first->customer_id,
            'currency_id' => $first->currency_id,
        ]);
    }

    $payment = allocationPayment($first, 1000);

    app(PaymentAllocationService::class)->replace($payment, [
        ['invoice_id' => $first->id, 'amount' => 300],
        ['invoice_id' => $second->id, 'amount' => 400],
        ['invoice_id' => $third->id, 'amount' => 200],
    ]);

    expect(PaymentAllocation::where('payment_id', $payment->id)->sum('amount'))->toBe(900)
        ->and($first->fresh()->due_amount)->toBe(0)
        ->and($second->fresh()->due_amount)->toBe(0)
        ->and($third->fresh()->due_amount)->toBe(0)
        ->and($payment->fresh()->amount - PaymentAllocation::where('payment_id', $payment->id)->sum('amount'))->toBe(100);
});

test('replacing allocations recalculates both old and new invoice balances', function () {
    $first = allocatableInvoice(500);
    $second = allocatableInvoice(500);
    $second->update([
        'company_id' => $first->company_id,
        'customer_id' => $first->customer_id,
        'currency_id' => $first->currency_id,
    ]);
    $payment = allocationPayment($first, 500);
    $service = app(PaymentAllocationService::class);

    $service->replace($payment, [['invoice_id' => $first->id, 'amount' => 500]]);
    $service->replace($payment, [['invoice_id' => $second->id, 'amount' => 500]]);

    expect($first->fresh()->due_amount)->toBe(500)
        ->and($first->fresh()->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($second->fresh()->due_amount)->toBe(0)
        ->and($second->fresh()->paid_status)->toBe(Invoice::STATUS_PAID);
});

test('allocation over an invoice balance is rejected without changing allocations', function () {
    $invoice = allocatableInvoice(100);
    $payment = allocationPayment($invoice, 101);

    expect(fn () => app(PaymentAllocationService::class)->replace($payment, [
        ['invoice_id' => $invoice->id, 'amount' => 101],
    ]))->toThrow(ValidationException::class);

    expect(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($invoice->fresh()->due_amount)->toBe(100);
});

test('allocations reject mismatched currencies, customers, drafts, and credit notes', function () {
    $invoice = allocatableInvoice(100);
    $payment = allocationPayment($invoice, 100);
    $otherCustomer = Customer::factory()->create([
        'company_id' => $invoice->company_id,
        'currency_id' => $invoice->currency_id,
    ]);
    $otherCurrency = Currency::query()->whereKeyNot($invoice->currency_id)->firstOrFail();

    $invalidInvoices = collect([
        allocatableInvoice(100)->forceFill([
            'company_id' => $invoice->company_id,
            'customer_id' => $otherCustomer->id,
            'currency_id' => $invoice->currency_id,
        ]),
        allocatableInvoice(100)->forceFill([
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'currency_id' => $otherCurrency->id,
        ]),
        allocatableInvoice(100)->forceFill([
            ...$invoice->only(['company_id', 'customer_id', 'currency_id']),
            'status' => Invoice::STATUS_DRAFT,
        ]),
        allocatableInvoice(100)->forceFill([
            ...$invoice->only(['company_id', 'customer_id', 'currency_id']),
            'type' => Invoice::TYPE_CREDIT_NOTE,
        ]),
    ])->each->save();

    foreach ($invalidInvoices as $invalidInvoice) {
        expect(fn () => app(PaymentAllocationService::class)->replace($payment, [[
            'invoice_id' => $invalidInvoice->id,
            'amount' => 100,
        ]]))->toThrow(ValidationException::class);
    }

    expect($payment->allocations()->exists())->toBeFalse();
});

test('duplicate targets and totals above the payment amount are rejected', function () {
    $first = allocatableInvoice(100);
    $second = allocatableInvoice(100);
    $second->update([
        'company_id' => $first->company_id,
        'customer_id' => $first->customer_id,
        'currency_id' => $first->currency_id,
    ]);
    $payment = allocationPayment($first, 100);
    $service = app(PaymentAllocationService::class);

    expect(fn () => $service->replace($payment, [
        ['invoice_id' => $first->id, 'amount' => 50],
        ['invoice_id' => $first->id, 'amount' => 50],
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $service->replace($payment, [
            ['invoice_id' => $first->id, 'amount' => 60],
            ['invoice_id' => $second->id, 'amount' => 50],
        ]))->toThrow(ValidationException::class);
});

test('payment PDF generation is deferred until a successful allocation transaction commits', function () {
    Queue::fake();
    $invoice = allocatableInvoice(100);

    DB::transaction(function () use ($invoice): void {
        $payment = allocationPayment($invoice, 100);
        app(PaymentAllocationService::class)->replace($payment, [
            ['invoice_id' => $invoice->id, 'amount' => 100],
        ]);

        Queue::assertNothingPushed();
    });

    Queue::assertPushed(GeneratePaymentPdfJob::class);

    Queue::fake();

    expect(fn () => DB::transaction(function () use ($invoice): void {
        $payment = allocationPayment($invoice, 100);

        app(PaymentAllocationService::class)->replace($payment, [
            ['invoice_id' => $invoice->id, 'amount' => 101],
        ]);
    }))->toThrow(ValidationException::class);

    Queue::assertNothingPushed();
});

test('customer credit can be applied atomically to multiple invoices', function () {
    $first = allocatableInvoice(100);
    $second = allocatableInvoice(200);
    $second->update([
        'company_id' => $first->company_id,
        'customer_id' => $first->customer_id,
        'currency_id' => $first->currency_id,
    ]);
    $payment = allocationPayment($first, 300);

    app(PaymentAllocationService::class)->applyCustomerCredits(
        $first->company_id,
        $first->customer_id,
        [
            ['payment_id' => $payment->id, 'invoice_id' => $first->id, 'amount' => 100],
            ['payment_id' => $payment->id, 'invoice_id' => $second->id, 'amount' => 200],
        ],
    );

    expect(PaymentAllocation::where('payment_id', $payment->id)->sum('amount'))->toBe(300)
        ->and($first->fresh()->due_amount)->toBe(0)
        ->and($second->fresh()->due_amount)->toBe(0);
});

test('creating payments does not authorize reallocating existing customer credit', function () {
    $invoice = allocatableInvoice(100);
    $payment = allocationPayment($invoice, 100);
    $user = User::factory()->create();
    $user->companies()->attach($invoice->company_id);

    BouncerFacade::scope()->to($invoice->company_id);
    BouncerFacade::allow($user)->to('view-customer', Customer::class);
    BouncerFacade::allow($user)->to('create-payment', Payment::class);
    Sanctum::actingAs($user, ['*']);
    $this->withHeaders(['company' => $invoice->company_id]);

    $this->postJson("/api/v1/customers/{$invoice->customer_id}/credit-allocations", [
        'allocations' => [[
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
        ]],
    ])->assertForbidden();
});

test('the migration restores an invalid legacy link to unapplied credit and recalculates its invoice', function () {
    $invoice = allocatableInvoice(100);
    $invoice->update([
        'due_amount' => 0,
        'base_due_amount' => 0,
        'status' => Invoice::STATUS_COMPLETED,
        'paid_status' => Invoice::STATUS_PAID,
    ]);
    $otherCustomer = Customer::factory()->create([
        'company_id' => $invoice->company_id,
        'currency_id' => $invoice->currency_id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $otherCustomer->id,
        'currency_id' => $invoice->currency_id,
        'amount' => 100,
        'base_amount' => 100,
        'exchange_rate' => 1,
    ]);

    Schema::table('customer_payments', fn ($table) => $table->unsignedInteger('invoice_id')->nullable()->index());
    Payment::query()->whereKey($payment->id)->update(['invoice_id' => $invoice->id]);

    withLegacyPaymentStorage(fn () => (require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php'))->up());

    expect(Schema::hasColumn('customer_payments', 'invoice_id'))->toBeFalse()
        ->and(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($invoice->fresh()->due_amount)->toBe(100)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->fresh()->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('the migration leaves an invalid legacy credit note target unchanged', function () {
    $creditNote = allocatableInvoice(100);
    $creditNote->update([
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'due_amount' => 100,
        'base_due_amount' => 100,
    ]);
    $payment = allocationPayment($creditNote, 100);

    Schema::table('customer_payments', fn ($table) => $table->unsignedInteger('invoice_id')->nullable()->index());
    Payment::query()->whereKey($payment->id)->update(['invoice_id' => $creditNote->id]);

    withLegacyPaymentStorage(fn () => (require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php'))->up());

    expect(Schema::hasColumn('customer_payments', 'invoice_id'))->toBeFalse()
        ->and(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($creditNote->fresh()->status)->toBe(Invoice::STATUS_SENT)
        ->and($creditNote->fresh()->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($creditNote->fresh()->due_amount)->toBe(100);
});

test('the migration allocates only the payable portion of an overpaid legacy payment', function () {
    $invoice = allocatableInvoice(100);
    $payment = allocationPayment($invoice, 150);

    Schema::table('customer_payments', fn ($table) => $table->unsignedInteger('invoice_id')->nullable()->index());
    Payment::query()->whereKey($payment->id)->update(['invoice_id' => $invoice->id]);

    withLegacyPaymentStorage(fn () => (require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php'))->up());

    expect(PaymentAllocation::where('payment_id', $payment->id)->sum('amount'))->toBe(100)
        ->and($invoice->fresh()->due_amount)->toBe(0)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($payment->amount - PaymentAllocation::where('payment_id', $payment->id)->sum('amount'))->toBe(50);
});

test('the migration refuses rollback when a payment retains unapplied credit', function () {
    $invoice = allocatableInvoice(100);
    $payment = allocationPayment($invoice, 150);
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'base_amount' => 100,
    ]);

    $migration = require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php');

    expect(fn () => withLegacyPaymentStorage(fn () => $migration->down()))->toThrow(RuntimeException::class, 'Cannot roll back payment allocations with unapplied customer credit.');
});

test('a partial migration run with no legacy column still verifies existing allocations', function () {
    $invoice = Invoice::factory()->create([
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_DRAFT,
    ]);
    $payment = allocationPayment($invoice, 100);
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'base_amount' => 100,
    ]);

    $migration = require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php');

    expect(Schema::hasColumn('customer_payments', 'invoice_id'))->toBeFalse()
        ->and(fn () => withLegacyPaymentStorage(fn () => $migration->up()))->toThrow(RuntimeException::class, 'allocation target is not payable');
});

test('a partial migration run rejects allocations whose payment no longer exists', function () {
    $invoice = allocatableInvoice(100);
    PaymentAllocation::create([
        'payment_id' => 999999,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'base_amount' => 100,
    ]);

    $migration = require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php');

    expect(Schema::hasColumn('customer_payments', 'invoice_id'))->toBeFalse()
        ->and(fn () => withLegacyPaymentStorage(fn () => $migration->up()))->toThrow(RuntimeException::class, 'allocation payment is missing');
});
