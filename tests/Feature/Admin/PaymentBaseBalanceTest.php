<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Bus::fake();
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('deleting a payment restores the base balance using the invoice exchange rate', function ($status, $sent, $viewed, $rate, $amount, $due, $baseDue) {
    $invoice = Invoice::factory()->create([
        'recurring_invoice_id' => null,
        'status' => $status,
        'sent' => $sent,
        'viewed' => $viewed,
        'total' => 10000,
        'due_amount' => $due,
        'base_total' => (int) round(10000 * $rate),
        'base_due_amount' => $baseDue,
        'exchange_rate' => $rate,
        'paid_status' => $due === 0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID,
    ]);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => $amount,
        'exchange_rate' => 2,
        'base_amount' => $amount * 2,
    ]);

    $this->postJson('/api/v1/payments/delete', ['ids' => [$payment->id]])
        ->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'due_amount' => $due + $amount,
        'base_due_amount' => (int) round(($due + $amount) * $rate),
        'paid_status' => Invoice::STATUS_UNPAID,
        'status' => $viewed ? Invoice::STATUS_VIEWED : ($sent ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT),
    ]);
})->with([
    'same currency, partial payment' => [Invoice::STATUS_SENT, true, false, 1, 4000, 6000, 6000],
    'same currency, full payment' => [Invoice::STATUS_COMPLETED, true, false, 1, 10000, 0, 0],
    'draft remains draft' => [Invoice::STATUS_DRAFT, false, false, 1, 4000, 6000, 6000],
    'viewed remains viewed' => [Invoice::STATUS_VIEWED, true, true, 1, 4000, 6000, 6000],
    'fractional invoice rate' => [Invoice::STATUS_SENT, true, false, 1.125, 4000, 6000, 6750],
]);

test('deleting one payment preserves the remaining payment and converts the remaining balance once', function () {
    $invoice = Invoice::factory()->create([
        'recurring_invoice_id' => null,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
        'total' => 10000,
        'due_amount' => 1,
        'base_total' => 11250,
        'base_due_amount' => 1,
        'exchange_rate' => 1.125,
    ]);
    $remaining = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 9995,
    ]);
    $deleted = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 4,
    ]);

    $this->postJson('/api/v1/payments/delete', ['ids' => [$deleted->id]])->assertOk();

    $this->assertDatabaseHas('payments', ['id' => $remaining->id, 'amount' => 9995]);
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'due_amount' => 5,
        'base_due_amount' => 6,
        'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
        'status' => Invoice::STATUS_SENT,
    ]);
});
