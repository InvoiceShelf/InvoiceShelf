<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Http\Controllers\Company\InvoicesController;
use App\Domains\Sales\Http\Requests\ChangeInvoiceStatusRequest;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
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

function completionInvoice(int $total = 10000, ?int $dueAmount = null, ?int $baseDueAmount = null): Invoice
{
    $dueAmount ??= $total;
    $baseDueAmount ??= $dueAmount;

    return Invoice::factory()->create([
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'total' => $total,
        'due_amount' => $dueAmount,
        'base_total' => $total,
        'base_due_amount' => $baseDueAmount,
        'exchange_rate' => 1,
    ]);
}

test('testGetInvoices', function () {
    $response = getJson('api/v1/invoices?page=1&type=OVERDUE&limit=20');

    $response->assertOk();
});

test('cannot convert an invoice belonging to another company', function () {
    $invoice = Invoice::factory()->create(['company_id' => Company::factory()->create()->id]);

    postJson("api/v1/invoices/{$invoice->id}/convert-to-estimate")->assertStatus(403);
});

test('create invoice', function () {
    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [InvoiceItem::factory()->raw()],
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'template_name' => $invoice['template_name'],
        'invoice_number' => $invoice['invoice_number'],
        'customer_id' => $invoice['customer_id'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'item_id' => $invoice['items'][0]['item_id'],
        'name' => $invoice['items'][0]['name'],
    ]);
});

test('server recomputes invoice totals and ignores client-supplied amounts', function () {
    // Well-formed item (10 x 10000 = 100000) but every client-supplied total is
    // tampered to 1 — the server must recompute from price/quantity (GHSA-8c69).
    $item = InvoiceItem::factory()->raw([
        'price' => 10000,
        'quantity' => 10,
        'total' => 1,
        'discount_val' => 0,
        'tax' => 0,
        'taxes' => [],
    ]);

    $invoice = Invoice::factory()->raw([
        'items' => [$item],
        'taxes' => [],
        'discount_val' => 0,
        'tax_included' => false,
        'sub_total' => 1,
        'total' => 1,
        'tax' => 0,
        'due_amount' => 1,
    ]);

    postJson('api/v1/invoices', $invoice)->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'sub_total' => 100000,
        'total' => 100000,
        'due_amount' => 100000,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'name' => $item['name'],
        'total' => 100000,
    ]);
});

test('persists an inclusive compound tax on top of the entered gross total', function () {
    $simpleTax = Tax::factory()->raw([
        'name' => 'VAT 19%',
        'percent' => 19,
        'amount' => 1900,
        'compound_tax' => false,
    ]);
    $compoundTax = Tax::factory()->raw([
        'name' => 'Cash levy 1%',
        'percent' => 1,
        'amount' => 119,
        'compound_tax' => true,
    ]);
    $item = InvoiceItem::factory()->raw([
        'price' => 11900,
        'quantity' => 1,
        'discount' => 0,
        'discount_val' => 0,
        'tax' => 0,
        'taxes' => [],
    ]);
    $invoice = Invoice::factory()->raw([
        'items' => [$item],
        'taxes' => [$simpleTax, $compoundTax],
        'discount' => 0,
        'discount_val' => 0,
        'tax_included' => true,
        'sub_total' => 1,
        'tax' => 1,
        'total' => 1,
        'due_amount' => 1,
    ]);

    postJson('api/v1/invoices', $invoice)->assertOk();

    $savedInvoice = Invoice::query()
        ->where('invoice_number', $invoice['invoice_number'])
        ->firstOrFail();

    expect($savedInvoice->sub_total)->toBe(11900)
        ->and($savedInvoice->tax)->toBe(2019)
        ->and($savedInvoice->total)->toBe(12019)
        ->and($savedInvoice->due_amount)->toBe(12019)
        ->and((bool) $savedInvoice->tax_included)->toBeTrue();

    $this->assertDatabaseHas('taxes', [
        'invoice_id' => $savedInvoice->id,
        'tax_type_id' => $simpleTax['tax_type_id'],
        'amount' => 1900,
        'compound_tax' => 0,
    ]);
    $this->assertDatabaseHas('taxes', [
        'invoice_id' => $savedInvoice->id,
        'tax_type_id' => $compoundTax['tax_type_id'],
        'amount' => 119,
        'compound_tax' => 1,
    ]);
});

test('create invoice with negative and zero item quantities', function () {
    $invoice = Invoice::factory()->raw([
        'items' => [
            InvoiceItem::factory()->raw([
                'quantity' => -2,
                'price' => 100,
            ]),
            InvoiceItem::factory()->raw([
                'quantity' => 1,
                'price' => 50,
            ]),
            InvoiceItem::factory()->raw([
                'quantity' => 0,
                'price' => 75,
            ]),
        ],
        'discount_val' => 0,
        'sub_total' => -150,
        'total' => -150,
    ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'total' => -150,
        'sub_total' => -150,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'quantity' => -2,
        'total' => -200,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'quantity' => 1,
        'total' => 50,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'quantity' => 0,
        'total' => 0,
    ]);

    $createdInvoice = Invoice::where('total', -150)->first();
    $this->assertNotNull($createdInvoice);
    $this->assertEquals(3, $createdInvoice->items()->count());

    $negativeItem = $createdInvoice->items()->where('quantity', -2)->first();
    $this->assertNotNull($negativeItem);
    $this->assertEquals(-200, $negativeItem->total);

    $positiveItem = $createdInvoice->items()->where('quantity', 1)->first();
    $this->assertNotNull($positiveItem);
    $this->assertEquals(50, $positiveItem->total);

    $zeroItem = $createdInvoice->items()->where('quantity', 0)->first();
    $this->assertNotNull($zeroItem);
    $this->assertEquals(0, $zeroItem->total);
});

test('create invoice as sent', function () {
    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [InvoiceItem::factory()->raw()],
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'customer_id' => $invoice['customer_id'],
        'template_name' => $invoice['template_name'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'item_id' => $invoice['items'][0]['item_id'],
        'name' => $invoice['items'][0]['name'],
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        InvoicesController::class,
        'store',
        InvoicesRequest::class
    );
});

test('update invoice', function () {
    $invoice = Invoice::factory()->create([
        'invoice_date' => '1988-07-18',
        'due_date' => '1988-08-18',
    ]);

    $invoice2 = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [InvoiceItem::factory()->raw()],
        ]);

    putJson('api/v1/invoices/'.$invoice->id, $invoice2)->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice2['invoice_number'],
        'customer_id' => $invoice2['customer_id'],
        'template_name' => $invoice2['template_name'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'item_id' => $invoice2['items'][0]['item_id'],
        'name' => $invoice2['items'][0]['name'],
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        InvoicesController::class,
        'update',
        InvoicesRequest::class
    );
});

test('send invoice to customer', function () {
    Mail::fake();

    $invoices = Invoice::factory()->create([
        'invoice_date' => '1988-07-18',
        'due_date' => '1988-08-18',
    ]);

    $data = [
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
        'subject' => 'email subject',
        'body' => 'email body',
    ];

    $response = postJson('api/v1/invoices/'.$invoices->id.'/send', $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $invoice2 = Invoice::find($invoices->id);

    $this->assertEquals($invoice2->status, Invoice::STATUS_SENT);
    Mail::assertSent(SendInvoiceMail::class);
});

test('invoice status controller uses the change invoice status request', function () {
    $this->assertActionUsesFormRequest(
        InvoicesController::class,
        'changeStatus',
        ChangeInvoiceStatusRequest::class
    );
});

test('cannot complete an outstanding invoice', function () {
    $invoice = completionInvoice();

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'invoice_must_be_settled_before_completion');

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(10000)
        ->and((int) $invoice->base_due_amount)->toBe(10000)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->payments)->toHaveCount(0);
});

test('cannot complete a partially paid invoice', function () {
    $invoice = completionInvoice(10000, 5000, 5000);
    $payment = Payment::factory()->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $invoice->customer_id,
        'amount' => 5000,
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 5000,
        'base_amount' => 5000,
    ]);

    $invoice->update(['paid_status' => Invoice::STATUS_PARTIALLY_PAID]);

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'invoice_must_be_settled_before_completion');

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(5000)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID)
        ->and($invoice->payments->modelKeys())->toBe([$payment->id]);
});

test('cannot complete an invoice with an inconsistent zero stored due amount', function () {
    $invoice = completionInvoice(10000, 0, 10000);

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'invoice_must_be_settled_before_completion');

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(0)
        ->and((int) $invoice->base_due_amount)->toBe(10000)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT)
        ->and($invoice->payments)->toHaveCount(0);
});

test('completes a fully paid invoice idempotently without removing its payment', function () {
    $invoice = completionInvoice(10000, 0, 0);
    $payment = Payment::factory()->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $invoice->customer_id,
        'amount' => 10000,
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 10000,
        'base_amount' => 10000,
    ]);

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertOk();
    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->overdue)->toBe(0)
        ->and($invoice->payments->modelKeys())->toBe([$payment->id]);
});

test('completes a zero value invoice', function () {
    $invoice = completionInvoice(0, 0, 0);

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->overdue)->toBe(0);
});

test('invoice status requires a supported value', function () {
    $invoice = completionInvoice();

    postJson("api/v1/invoices/{$invoice->id}/status")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => 'DRAFT'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('invoice mark as sent', function () {
    $invoice = Invoice::factory()->create([
        'invoice_date' => '1988-07-18',
        'due_date' => '1988-08-18',
    ]);

    $data = [
        'status' => Invoice::STATUS_SENT,
    ];

    $response = postJson('api/v1/invoices/'.$invoice->id.'/status', $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertEquals(Invoice::find($invoice->id)->status, Invoice::STATUS_SENT);
});

test('search invoices', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'status' => Invoice::STATUS_DRAFT,
        'from_date' => '2019-01-20',
        'to_date' => '2019-01-27',
        'invoice_number' => '000012',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/invoices?'.$queryString);

    $response->assertOk();
});

test('delete multiple invoices', function () {
    $invoices = Invoice::factory()->count(3)->create([
        'invoice_date' => '1988-07-18',
        'due_date' => '1988-08-18',
    ]);

    $ids = $invoices->pluck('id');

    $data = [
        'ids' => $ids,
    ];

    postJson('api/v1/invoices/delete', $data)
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    foreach ($invoices as $invoice) {
        $this->assertModelMissing($invoice);
    }
});

test('clone invoice', function () {
    $invoices = Invoice::factory()->create([
        'invoice_date' => '1988-07-18',
        'due_date' => '1988-08-18',
    ]);

    postJson("api/v1/invoices/{$invoices->id}/clone")
        ->assertStatus(201);
});

test('create invoice with negative tax', function () {
    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw([
                'percent' => -9.99,
            ])],
            'items' => [InvoiceItem::factory()->raw()],
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'customer_id' => $invoice['customer_id'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'name' => $invoice['items'][0]['name'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $invoice['taxes'][0]['tax_type_id'],
    ]);
});

test('create invoice with tax per item', function () {
    $invoice = Invoice::factory()
        ->raw([
            'tax_per_item' => 'YES',
            'items' => [
                InvoiceItem::factory()->raw([
                    'taxes' => [Tax::factory()->raw()],
                ]),
                InvoiceItem::factory()->raw([
                    'taxes' => [Tax::factory()->raw()],
                ]),
            ],
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'customer_id' => $invoice['customer_id'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'name' => $invoice['items'][0]['name'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $invoice['items'][0]['taxes'][0]['tax_type_id'],
    ]);
});

test('create invoice with tax per item ignores empty placeholder tax row', function () {
    // The frontend always keeps one empty placeholder tax row per item in
    // per-item tax mode. It must be skipped instead of reaching the DB.
    $stubTax = [
        'id' => 999,
        'tax_type_id' => 0,
        'name' => '',
        'amount' => 0,
        'percent' => null,
        'calculation_type' => null,
        'fixed_amount' => 0,
        'compound_tax' => false,
    ];

    $realTax = Tax::factory()->raw();

    $invoice = Invoice::factory()
        ->raw([
            'tax_per_item' => 'YES',
            'items' => [
                InvoiceItem::factory()->raw([
                    'taxes' => [$realTax, $stubTax],
                ]),
            ],
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'customer_id' => $invoice['customer_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $realTax['tax_type_id'],
        'amount' => $realTax['amount'],
    ]);

    $this->assertDatabaseMissing('taxes', [
        'tax_type_id' => 0,
    ]);
});

test('rejects a nonzero per-item placeholder tax row', function () {
    $invoice = Invoice::factory()->raw([
        'items' => [
            InvoiceItem::factory()->raw([
                'taxes' => [[
                    'tax_type_id' => 0,
                    'amount' => 1,
                ]],
            ]),
        ],
    ]);

    postJson('api/v1/invoices', $invoice)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.taxes.0.amount');
});

test('create invoice with EUR currency', function () {
    $invoice = Invoice::factory()
        ->raw([
            'discount_type' => 'fixed',
            'discount_val' => 20,
            'sub_total' => 100,
            'total' => 84,
            'tax' => 4,
            'due_amount' => 84,
            'exchange_rate' => 86.403538,
            'base_discount_val' => 1728.07,
            'base_sub_total' => 8640.35,
            'base_total' => 7257.90,
            'base_tax' => 345.61,
            'base_due_amount' => 7257.90,
            'taxes' => [Tax::factory()->raw([
                'amount' => 4,
                'percent' => 5,
                'base_amount' => 345.61,
            ])],
            'items' => [InvoiceItem::factory()->raw([
                'discount_type' => 'fixed',
                'price' => 100,
                'quantity' => 1,
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'total' => 100,
                'base_price' => 8640.35,
                'exchange_rate' => 86.403538,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => 8640.35,
            ])],
        ]);

    $response = postJson('api/v1/invoices', $invoice)->assertOk();

    $this->assertDatabaseHas('invoices', [
        'template_name' => $invoice['template_name'],
        'invoice_number' => $invoice['invoice_number'],
        'sub_total' => $invoice['sub_total'],
        'discount' => $invoice['discount'],
        'customer_id' => $invoice['customer_id'],
        'total' => $invoice['total'],
        'tax' => $invoice['tax'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $invoice['taxes'][0]['tax_type_id'],
        'amount' => $invoice['tax'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'item_id' => $invoice['items'][0]['item_id'],
        'name' => $invoice['items'][0]['name'],
    ]);
});

test('update invoice with EUR currency', function () {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->hasTaxes(1)
        ->create([
            'invoice_date' => '1988-07-18',
            'due_date' => '1988-08-18',
        ]);

    $invoice2 = Invoice::factory()
        ->raw([
            'id' => $invoice['id'],
            'discount_type' => 'fixed',
            'discount_val' => 20,
            'sub_total' => 100,
            'total' => 84,
            'tax' => 4,
            'due_amount' => 84,
            'exchange_rate' => 86.403538,
            'base_discount_val' => 1728.07,
            'base_sub_total' => 8640.35,
            'base_total' => 7257.897192,
            'base_tax' => 345.614152,
            'base_due_amount' => 7257.897192,
            'taxes' => [Tax::factory()->raw([
                'tax_type_id' => $invoice->taxes[0]->tax_type_id,
                'amount' => 4,
                'percent' => 5,
                'base_amount' => 345.614152,
            ])],
            'items' => [InvoiceItem::factory()->raw([
                'invoice_id' => $invoice->id,
                'discount_type' => 'fixed',
                'price' => 100,
                'quantity' => 1,
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'total' => 100,
                'base_price' => 8640.3538,
                'exchange_rate' => 86.403538,
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => 8640.3538,
            ])],
        ]);

    putJson('api/v1/invoices/'.$invoice->id, $invoice2)->assertOk();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice['id'],
        'invoice_number' => $invoice2['invoice_number'],
        'sub_total' => $invoice2['sub_total'],
        'total' => $invoice2['total'],
        'tax' => $invoice2['tax'],
        'discount' => $invoice2['discount'],
        'customer_id' => $invoice2['customer_id'],
        'template_name' => $invoice2['template_name'],
        'exchange_rate' => $invoice2['exchange_rate'],
        'base_total' => $invoice2['base_total'],
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice2['items'][0]['invoice_id'],
        'item_id' => $invoice2['items'][0]['item_id'],
        'name' => $invoice2['items'][0]['name'],
        'exchange_rate' => $invoice2['items'][0]['exchange_rate'],
        'base_price' => $invoice2['items'][0]['base_price'],
        'base_total' => $invoice2['items'][0]['base_total'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'amount' => $invoice2['taxes'][0]['amount'],
        'name' => $invoice2['taxes'][0]['name'],
        'base_amount' => $invoice2['taxes'][0]['base_amount'],
    ]);
});

test('create invoice with tax included', function () {
    $invoice = Invoice::factory()
        ->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [InvoiceItem::factory()->raw()],
            'tax_included' => true,
        ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'tax_included' => true,
    ]);
});

/**
 * The fallback for a company with no tax-per-item setting used to be 'NO '
 * with a trailing space, which matched neither YES nor NO, so the form showed
 * no tax controls at all.
 */
test('an invoice for a company with no tax-per-item setting taxes the whole invoice', function () {
    $companyId = User::find(1)->companies()->first()->id;
    CompanySetting::where('company_id', $companyId)->where('option', 'tax_per_item')->delete();

    $invoice = Invoice::factory()->raw([
        'items' => [InvoiceItem::factory()->raw(['price' => 10000, 'quantity' => 1, 'discount_val' => 0, 'tax' => 0, 'taxes' => []])],
        'taxes' => [],
        'discount_val' => 0,
        'tax_included' => false,
    ]);

    postJson('api/v1/invoices', $invoice)->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'tax_per_item' => 'NO',
    ]);
});
