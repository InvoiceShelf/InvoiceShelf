<?php

use App\Http\Controllers\Company\Payment\PaymentsController;
use App\Http\Requests\PaymentRequest;
use App\Mail\SendPaymentMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Document\CreditNoteService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

/**
 * An unpaid invoice whose stored totals agree with its line items.
 *
 * The credit-note calculator derives everything from the original invoice's
 * stored figures, so a fixture whose total has nothing to do with its items
 * describes an invoice that could not exist. The amounts are pinned rather than
 * drawn from the factory because the payment cap is now compared against them.
 */
function payableInvoice(int $lines = 2, int $price = 10000): Invoice
{
    $total = $lines * $price;

    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'tax_included' => false,
        'discount' => 0,
        'discount_type' => 'fixed',
        'discount_val' => 0,
        'tax' => 0,
        'sub_total' => $total,
        'total' => $total,
        'due_amount' => $total,
        'exchange_rate' => 1,
        'base_sub_total' => $total,
        'base_discount_val' => 0,
        'base_tax' => 0,
        'base_total' => $total,
        'base_due_amount' => $total,
    ]);

    for ($index = 0; $index < $lines; $index++) {
        $invoice->items()->create([
            'name' => 'Line '.($index + 1),
            'quantity' => 1,
            'price' => $price,
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
            'total' => $price,
            'company_id' => $invoice->company_id,
            'exchange_rate' => 1,
            'base_price' => $price,
            'base_discount_val' => 0,
            'base_tax' => 0,
            'base_total' => $price,
        ]);
    }

    return $invoice->fresh();
}

/**
 * A payment payload aimed at an invoice, with everything the cap depends on
 * pinned so the request is decided by the amount alone.
 */
function paymentPayloadFor(Invoice $invoice, int $amount): array
{
    return Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'currency_id' => $invoice->currency_id,
        'exchange_rate' => 1,
        'amount' => $amount,
    ]);
}

test('get payments', function () {
    $response = getJson('api/v1/payments?page=1');

    $response->assertOk();
});

test('get payment', function () {
    $payment = Payment::factory()->create();

    $response = getJson("api/v1/payments/{$payment->id}");

    $response->assertStatus(200);
});

test('create payment', function () {
    $invoice = Invoice::factory()->create([
        'due_amount' => 100,
        'exchange_rate' => 1,
    ]);

    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-000001',
        'amount' => $invoice->due_amount,
        'exchange_rate' => 1,
    ]);

    $response = postJson('api/v1/payments', $payment);

    $response->assertOk();

    $this->assertDatabaseHas('payments', [
        'payment_number' => $payment['payment_number'],
        'customer_id' => $payment['customer_id'],
        'amount' => $payment['amount'],
        'company_id' => $payment['company_id'],
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        PaymentsController::class,
        'store',
        PaymentRequest::class
    );
});

test('update payment', function () {
    // Every amount here is pinned. The factories draw random single-digit
    // amounts, and a payment is now capped at the invoice's due amount plus the
    // edited payment's own amount, so a random invoice total paired with two
    // random payment amounts overpays the invoice at random.
    $invoice = Invoice::factory()->create([
        'sub_total' => 10000,
        'total' => 10000,
        'due_amount' => 4000,
        'exchange_rate' => 1,
        'base_sub_total' => 10000,
        'base_total' => 10000,
        'base_due_amount' => 4000,
    ]);

    $payment = Payment::factory()->create([
        'payment_date' => '1988-08-18',
        'invoice_id' => $invoice->id,
        'exchange_rate' => 1,
        'amount' => 6000,
    ]);

    // The edited payment's own amount returns to the pool, so the whole invoice
    // total is payable again.
    $payment2 = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'exchange_rate' => 1,
        'amount' => 10000,
    ]);

    putJson("api/v1/payments/{$payment->id}", $payment2)
        ->assertOk();

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'payment_number' => $payment2['payment_number'],
        'customer_id' => $payment2['customer_id'],
        'amount' => $payment2['amount'],
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        PaymentsController::class,
        'update',
        PaymentRequest::class
    );
});

test('search payments', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'payment_number' => 'PAY-000001',
        'payment_mode' => 'OTHER',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/payments?'.$queryString);

    $response->assertOk();
});

test('send payment to customer', function () {
    Mail::fake();

    $payment = Payment::factory()->create();

    $data = [
        'subject' => 'test',
        'body' => 'test',
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
    ];

    $response = postJson("api/v1/payments/{$payment->id}/send", $data);

    $response->assertJson([
        'success' => true,
    ]);

    Mail::assertSent(SendPaymentMail::class);
});

test('delete payment', function () {
    $payments = Payment::factory()->count(5)->create();

    $ids = $payments->pluck('id');

    $data = [
        'ids' => $ids,
    ];

    $response = postJson('api/v1/payments/delete', $data);

    $response->assertJson([
        'success' => true,
    ]);
});

test('create payment without invoice', function () {
    $payment = Payment::factory()->raw([
        'payment_number' => 'PAY-000001',
        'exchange_rate' => 1,
    ]);

    postJson('api/v1/payments', $payment)->assertOk();

    $this->assertDatabaseHas('payments', [
        'payment_number' => $payment['payment_number'],
        'customer_id' => $payment['customer_id'],
        'amount' => $payment['amount'],
        'company_id' => $payment['company_id'],
    ]);
});

test('create payment with invoice', function () {
    $payment = Payment::factory()->raw([
        'payment_number' => 'PAY-000001',
    ]);

    $invoice = Invoice::factory()->create();

    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->due_amount,
        'exchange_rate' => 1,
    ]);

    postJson('api/v1/payments', $payment)->assertOk();

    $this->assertDatabaseHas('payments', [
        'payment_number' => $payment['payment_number'],
        'customer_id' => $payment['customer_id'],
        'invoice_id' => $payment['invoice_id'],
        'amount' => $payment['amount'],
        'company_id' => $payment['company_id'],
    ]);
});

test('create payment with partially paid', function () {
    $invoice = Invoice::factory()->create([
        'sub_total' => 100,
        'total' => 100,
        'due_amount' => 100,
        'exchange_rate' => 1,
        'base_discount_val' => 100,
        'base_sub_total' => 100,
        'base_total' => 100,
        'base_tax' => 100,
        'base_due_amount' => 100,
    ]);

    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'exchange_rate' => $invoice->exchange_rate,
        'amount' => 100,
        'currency_id' => $invoice->currency_id,
    ]);

    $response = postJson('api/v1/payments', $payment)->assertOk();

    $this->assertDatabaseHas('payments', [
        'payment_number' => $payment['payment_number'],
        'customer_id' => (string) $payment['customer_id'],
        'amount' => (string) $payment['amount'],
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice['id'],
        'invoice_number' => $response['data']['invoice']['invoice_number'],
        'total' => $response['data']['invoice']['total'],
        'customer_id' => $response['data']['invoice']['customer_id'],
        'exchange_rate' => $response['data']['invoice']['exchange_rate'],
        'base_total' => $response['data']['invoice']['base_total'],
        'paid_status' => $response['data']['invoice']['paid_status'],
    ]);
});

test('rejects a payment worth more than the invoice due amount', function () {
    // Regression: an overpayment used to be accepted and then silently lost.
    // PaymentService hands the amount to Invoice::subtractInvoicePayment(),
    // which drives the balance negative, and getInvoiceStatusByAmount() returns
    // nothing for a negative amount, so the invoice was left with a stale
    // balance and a status that never reflected the money taken.
    $invoice = payableInvoice();

    postJson('api/v1/payments', paymentPayloadFor($invoice, 20001))
        ->assertStatus(422)
        ->assertJsonPath('errors.amount.0', 'payment_amount_exceeds_invoice_due_amount');

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(20000)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);
});

test('accepts a payment worth exactly the invoice due amount', function () {
    $invoice = payableInvoice();

    postJson('api/v1/payments', paymentPayloadFor($invoice, 20000))
        ->assertOk();

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(0)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_COMPLETED);
});

test('rejects a payment worth more than the balance a partial credit note left', function () {
    $invoice = payableInvoice();
    $creditedItem = $invoice->items()->first();

    app(CreditNoteService::class)->create(
        $invoice,
        [['id' => $creditedItem->id, 'quantity' => 1]],
        null
    );

    $invoice->refresh();

    // Half the invoice was reversed, so half of it is still payable.
    expect((int) $invoice->due_amount)->toBe(10000);

    postJson('api/v1/payments', paymentPayloadFor($invoice, 10001))
        ->assertStatus(422)
        ->assertJsonPath('errors.amount.0', 'payment_amount_exceeds_invoice_due_amount');

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(10000);
});

test('accepts a payment worth exactly the balance a partial credit note left', function () {
    $invoice = payableInvoice();
    $creditedItem = $invoice->items()->first();

    app(CreditNoteService::class)->create(
        $invoice,
        [['id' => $creditedItem->id, 'quantity' => 1]],
        null
    );

    postJson('api/v1/payments', paymentPayloadFor($invoice->fresh(), 10000))
        ->assertOk();

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(0)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_COMPLETED);
});
