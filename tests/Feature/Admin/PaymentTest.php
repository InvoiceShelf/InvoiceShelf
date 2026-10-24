<?php

use App\Http\Controllers\V1\Admin\Payment\PaymentsController;
use App\Http\Requests\PaymentRequest;
use App\Mail\SendPaymentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
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

test('full payment preserves draft status and completes sent invoices', function (string $status, bool $sent, bool $viewed, string $expected_status) {
    $invoice = Invoice::factory()->create([
        'status' => $status,
        'sent' => $sent,
        'viewed' => $viewed,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 1000,
        'due_amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 1000,
        'exchange_rate' => 1,
    ]);

    $response = postJson('api/v1/payments', $payment)->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe($expected_status)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->due_amount)->toEqual(0);

    $this->assertDatabaseHas('payments', [
        'id' => $response->json('data.id'),
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);
})->with([
    'draft' => [Invoice::STATUS_DRAFT, false, false, Invoice::STATUS_DRAFT],
    'sent' => [Invoice::STATUS_SENT, true, false, Invoice::STATUS_COMPLETED],
    'viewed' => [Invoice::STATUS_VIEWED, true, true, Invoice::STATUS_COMPLETED],
]);

test('draft status survives payment increases reductions and deletion', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 1000,
        'due_amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 400,
        'exchange_rate' => 1,
    ]);
    $response = postJson('api/v1/payments', $payment)->assertOk();
    $payment_id = $response->json('data.id');

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID)
        ->and($invoice->due_amount)->toEqual(600);

    foreach ([1000, 250] as $amount) {
        $payment['amount'] = $amount;
        putJson('api/v1/payments/'.$payment_id, $payment)->assertOk();

        $invoice->refresh();

        expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
            ->and($invoice->paid_status)->toBe($amount === 1000 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID)
            ->and($invoice->due_amount)->toEqual(1000 - $amount);
    }

    postJson('api/v1/payments/delete', ['ids' => [$payment_id]])
        ->assertOk()->assertJson(['success' => true]);

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->due_amount)->toEqual(1000);

    $this->assertDatabaseMissing('payments', ['id' => $payment_id]);
});

test('moving a payment between drafts preserves both draft statuses', function () {
    $first_invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 1000,
        'due_amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $second_invoice = Invoice::factory()->create([
        'customer_id' => $first_invoice->customer_id,
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 1000,
        'due_amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $payment = Payment::factory()->raw([
        'invoice_id' => $first_invoice->id,
        'customer_id' => $first_invoice->customer_id,
        'amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $response = postJson('api/v1/payments', $payment)->assertOk();
    $payment_id = $response->json('data.id');
    $payment['invoice_id'] = $second_invoice->id;

    putJson('api/v1/payments/'.$payment_id, $payment)->assertOk();

    $first_invoice->refresh();
    $second_invoice->refresh();

    expect($first_invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($first_invoice->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($first_invoice->due_amount)->toEqual(1000)
        ->and($second_invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($second_invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($second_invoice->due_amount)->toEqual(0);

    $this->assertDatabaseHas('payments', [
        'id' => $payment_id,
        'invoice_id' => $second_invoice->id,
        'amount' => 1000,
    ]);
});

test('editing fully paid drafts respects retrospective edit settings', function (string $setting, bool $allowed) {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
        'viewed' => false,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => 1000,
        'due_amount' => 1000,
        'exchange_rate' => 1,
    ]);
    CompanySetting::setSettings(['retrospective_edits' => $setting], $invoice->company_id);
    $payment = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $response = postJson('api/v1/payments', $payment)->assertOk();
    $payment_id = $response->json('data.id');
    $original_notes = $invoice->notes;
    $update = Invoice::factory()->raw([
        'customer_id' => $invoice->customer_id,
        'invoice_number' => $invoice->invoice_number,
        'notes' => 'Updated paid draft.',
        'discount_type' => 'fixed',
        'discount_val' => 0,
        'items' => [InvoiceItem::factory()->raw(['price' => 1000, 'quantity' => 1])],
        'taxes' => [],
    ]);

    getJson('api/v1/invoices/'.$invoice->id)
        ->assertOk()->assertJsonPath('data.allow_edit', $allowed);

    $response = putJson('api/v1/invoices/'.$invoice->id, $update);

    if ($allowed) {
        $response->assertOk()->assertJsonPath('data.allow_edit', true);
    } else {
        $response->assertForbidden();
    }

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->due_amount)->toEqual(0)
        ->and($invoice->notes)->toBe($allowed ? $update['notes'] : $original_notes);

    $this->assertDatabaseHas('payments', [
        'id' => $payment_id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);
})->with([
    'edits allowed' => ['allow', true],
    'locked after sending' => ['disable_on_invoice_sent', false],
    'locked after partial payment' => ['disable_on_invoice_partial_paid', false],
    'locked after full payment' => ['disable_on_invoice_paid', false],
]);

test('editing a paid non-draft invoice keeps it completed', function (string $status, bool $sent, bool $viewed) {
    $invoice = Invoice::factory()->create([
        'status' => $status,
        'sent' => $sent,
        'viewed' => $viewed,
        'paid_status' => Invoice::STATUS_PAID,
        'total' => 1000,
        'due_amount' => 0,
        'exchange_rate' => 1,
    ]);
    CompanySetting::setSettings(['retrospective_edits' => 'allow'], $invoice->company_id);
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 1000,
        'exchange_rate' => 1,
    ]);
    $update = Invoice::factory()->raw([
        'customer_id' => $invoice->customer_id,
        'invoice_number' => $invoice->invoice_number,
        'notes' => 'Updated paid invoice.',
        'discount_type' => 'fixed',
        'discount_val' => 0,
        'sent' => $sent,
        'viewed' => $viewed,
        'items' => [InvoiceItem::factory()->raw(['price' => 1000, 'quantity' => 1])],
        'taxes' => [],
    ]);

    putJson('api/v1/invoices/'.$invoice->id, $update)->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->due_amount)->toEqual(0)
        ->and($invoice->notes)->toBe($update['notes']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 1000]);
})->with([
    'sent' => [Invoice::STATUS_SENT, true, false],
    'viewed' => [Invoice::STATUS_VIEWED, true, true],
    'completed after sending' => [Invoice::STATUS_COMPLETED, true, false],
    'explicitly completed without sending' => [Invoice::STATUS_COMPLETED, false, false],
]);

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        PaymentsController::class,
        'store',
        PaymentRequest::class
    );
});

test('update payment', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::factory()->create([
        'payment_date' => '1988-08-18',
        'invoice_id' => $invoice->id,
        'exchange_rate' => 1,
    ]);

    $payment2 = Payment::factory()->raw([
        'invoice_id' => $invoice->id,
        'exchange_rate' => 1,
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

test('a payment cannot name another company\'s invoice, customer or payment method', function () {
    $other = Company::factory()->create();
    $foreignInvoice = Invoice::factory()->create([
        'company_id' => $other->id,
        'customer_id' => Customer::factory()->create(['company_id' => $other->id])->id,
        'due_amount' => 100,
        'exchange_rate' => 1,
    ]);
    $foreignCustomer = Customer::factory()->create(['company_id' => $other->id]);
    $foreignMethod = PaymentMethod::factory()->create(['company_id' => $other->id]);

    foreach ([
        ['invoice_id' => $foreignInvoice->id],
        ['customer_id' => $foreignCustomer->id],
        ['payment_method_id' => $foreignMethod->id],
    ] as $foreign) {
        postJson('api/v1/payments', Payment::factory()->raw([
            'payment_number' => 'PAY-000001',
            'amount' => 50,
            'exchange_rate' => 1,
            ...$foreign,
        ]))->assertUnprocessable();
    }

    expect((int) $foreignInvoice->fresh()->due_amount)->toBe(100)
        ->and(Payment::where('invoice_id', $foreignInvoice->id)->exists())->toBeFalse();
});
