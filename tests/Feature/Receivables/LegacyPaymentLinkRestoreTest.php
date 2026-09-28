<?php

// The two halves of the draft-invoice rescue: the migration that files a
// declined legacy link instead of destroying it, and the command that applies
// the parked credit once the invoice is finally issued.

use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);
});

/**
 * An invoice in whatever stage the caller names, with every money column
 * consistent with a single outstanding total.
 */
function legacyLinkInvoice(int $total, string $status = Invoice::STATUS_DRAFT): Invoice
{
    return Invoice::factory()->create([
        'type' => Invoice::TYPE_INVOICE,
        'status' => $status,
        'sent' => $status !== Invoice::STATUS_DRAFT,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => $total,
        'total' => $total,
        'due_amount' => $total,
        'exchange_rate' => 3,
        'base_sub_total' => $total * 3,
        'base_total' => $total * 3,
        'base_due_amount' => $total * 3,
    ]);
}

/**
 * Money from the invoice's own contact, in the invoice's own currency.
 */
function legacyLinkPayment(Invoice $invoice, int $amount, string $notes = 'Cheque 4471.'): Payment
{
    return Payment::factory()->create([
        ...$invoice->only(['company_id', 'customer_id', 'currency_id']),
        'amount' => $amount,
        'base_amount' => $amount * 3,
        'exchange_rate' => 3,
        'notes' => $notes,
    ]);
}

/**
 * The row the migration would have filed for this pair.
 */
function legacyLinkRow(Payment $payment, Invoice $invoice, string $reason = 'draft'): void
{
    DB::table('legacy_payment_links')->insert([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'reason' => $reason,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * The note the migration appends to a payment it parks.
 */
function legacyLinkNote(Invoice $invoice): string
{
    return sprintf(
        'Recorded against draft invoice %s before the 3.x upgrade; retained as unapplied customer credit.',
        $invoice->invoice_number
    );
}

test('the command leaves a parked payment alone while its invoice is still a draft', function () {
    $invoice = legacyLinkInvoice(60);
    $payment = legacyLinkPayment($invoice, 100, 'Cheque 4471.'."\n".legacyLinkNote($invoice));
    legacyLinkRow($payment, $invoice);

    foreach ([['--dry-run' => true], []] as $options) {
        $this->artisan('payments:restore-legacy-links', $options)
            ->expectsOutputToContain('waiting-on-draft')
            ->assertExitCode(0);
    }

    expect(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and(DB::table('legacy_payment_links')->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($invoice->fresh()->due_amount)->toBe(60)
        ->and($payment->fresh()->notes)->toContain(legacyLinkNote($invoice));
});

test('a dry run reports the restorable payment without writing anything', function () {
    $invoice = legacyLinkInvoice(60);
    $payment = legacyLinkPayment($invoice, 100);
    legacyLinkRow($payment, $invoice);
    $invoice->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);

    $this->artisan('payments:restore-legacy-links', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('restored')
        ->assertExitCode(0);

    expect(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and(DB::table('legacy_payment_links')->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($invoice->fresh()->due_amount)->toBe(60)
        ->and($invoice->fresh()->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('the command applies the parked credit once the invoice is issued and forgets the link', function () {
    $invoice = legacyLinkInvoice(60);
    $payment = legacyLinkPayment($invoice, 100);
    legacyLinkRow($payment, $invoice);
    $invoice->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);

    $this->artisan('payments:restore-legacy-links')
        ->expectsOutputToContain('restored')
        ->assertExitCode(0);

    $allocation = PaymentAllocation::where('payment_id', $payment->id)->sole();

    // The service prorates the base amount: 300 base units of a 100 unit
    // payment, 60 of which land on this invoice.
    expect((int) $allocation->invoice_id)->toBe($invoice->id)
        ->and((int) $allocation->amount)->toBe(60)
        ->and((int) $allocation->base_amount)->toBe(180)
        ->and($invoice->fresh()->due_amount)->toBe(0)
        ->and($invoice->fresh()->base_due_amount)->toBe(0)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($invoice->fresh()->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and(DB::table('legacy_payment_links')->where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and((int) $payment->fresh()->amount - 60)->toBe(40);

    // A second sweep has nothing left to act on.
    $this->artisan('payments:restore-legacy-links')->assertExitCode(0);

    expect(PaymentAllocation::where('payment_id', $payment->id)->count())->toBe(1)
        ->and($invoice->fresh()->due_amount)->toBe(0);
});

test('a mismatched legacy link is reported but never repaired', function () {
    $invoice = legacyLinkInvoice(60, Invoice::STATUS_SENT);
    $stranger = Customer::factory()->create([
        'company_id' => $invoice->company_id,
        'currency_id' => $invoice->currency_id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $stranger->id,
        'currency_id' => $invoice->currency_id,
        'amount' => 100,
        'base_amount' => 300,
        'exchange_rate' => 3,
    ]);
    legacyLinkRow($payment, $invoice, 'mismatch');

    $this->artisan('payments:restore-legacy-links')
        ->expectsOutputToContain('mismatch-retained')
        ->assertExitCode(0);

    expect(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and(DB::table('legacy_payment_links')->where('payment_id', $payment->id)->value('reason'))->toBe('mismatch')
        ->and($invoice->fresh()->due_amount)->toBe(60);
});

test('the migration files a declined draft link, annotates the payment, and restores the invoice balance', function () {
    $invoice = legacyLinkInvoice(100);
    $invoice->update([
        'due_amount' => 0,
        'base_due_amount' => 0,
        'paid_status' => Invoice::STATUS_PAID,
    ]);
    $payment = legacyLinkPayment($invoice, 100, 'Cheque 4471.');

    Schema::table('customer_payments', fn ($table) => $table->unsignedInteger('invoice_id')->nullable()->index());
    DB::table('customer_payments')->where('id', $payment->id)->update(['invoice_id' => $invoice->id]);
    DB::table('migrations')
        ->where('migration', '2026_08_02_230400_replace_payment_invoice_with_allocations')
        ->delete();

    Log::spy();

    withLegacyPaymentStorage(fn () => Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php',
        '--force' => true,
    ]));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'Payment legacy invoice link was retained as unapplied customer credit.'
            && (int) ($context['payment_id'] ?? 0) === $payment->id)
        ->once();

    $link = DB::table('legacy_payment_links')->where('payment_id', $payment->id)->first();

    expect(Schema::hasColumn('customer_payments', 'invoice_id'))->toBeFalse()
        ->and(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($link)->not->toBeNull()
        ->and($link->reason)->toBe('draft')
        ->and((int) $link->invoice_id)->toBe($invoice->id)
        ->and($payment->fresh()->notes)->toBe('Cheque 4471.'."\n".legacyLinkNote($invoice))
        ->and($invoice->fresh()->due_amount)->toBe(100)
        ->and($invoice->fresh()->base_due_amount)->toBe(300)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->fresh()->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('the migration files a mismatched legacy link without annotating the payment', function () {
    $invoice = legacyLinkInvoice(100, Invoice::STATUS_SENT);
    $stranger = Customer::factory()->create([
        'company_id' => $invoice->company_id,
        'currency_id' => $invoice->currency_id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $stranger->id,
        'currency_id' => $invoice->currency_id,
        'amount' => 100,
        'base_amount' => 300,
        'exchange_rate' => 3,
        'notes' => 'Cheque 4471.',
    ]);

    Schema::table('customer_payments', fn ($table) => $table->unsignedInteger('invoice_id')->nullable()->index());
    DB::table('customer_payments')->where('id', $payment->id)->update(['invoice_id' => $invoice->id]);

    withLegacyPaymentStorage(fn () => (require database_path('migrations/2026_08_02_230400_replace_payment_invoice_with_allocations.php'))->up());

    expect(DB::table('legacy_payment_links')->where('payment_id', $payment->id)->value('reason'))->toBe('mismatch')
        ->and($payment->fresh()->notes)->toBe('Cheque 4471.')
        ->and(PaymentAllocation::where('payment_id', $payment->id)->exists())->toBeFalse();
});
