<?php

use App\Mail\SendCreditNoteMail;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tax;
use App\Models\User;
use App\Services\Document\CreditNoteService;
use App\Services\Document\InvoiceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
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
    Sanctum::actingAs($user, ['*']);
});

/**
 * Create an invoice whose stored document totals agree with its line items.
 *
 * That agreement is what every invoice the app writes has and what the
 * credit-note calculator reads: it derives the credit from the ORIGINAL
 * invoice's stored figures, so a fixture whose total has nothing to do with its
 * items describes an invoice that could not exist and produces credit notes to
 * match.
 *
 * @param  array  $lines  [['price' => int, 'quantity' => float, 'taxes' => [['amount' => int, 'percent' => float]]], ...]
 * @param  array  $attributes  invoice overrides (status, exchange_rate, discount_val, tax_per_item, tax_included, ...)
 * @param  array  $documentTaxes  document-level tax rows: [['amount' => int, 'percent' => float], ...]
 */
function creditableInvoice(array $lines = [['price' => 10000, 'quantity' => 1]], array $attributes = [], array $documentTaxes = []): Invoice
{
    $rate = $attributes['exchange_rate'] ?? 1;
    $taxPerItem = $attributes['tax_per_item'] ?? 'NO';
    $taxIncluded = $attributes['tax_included'] ?? false;
    $discountVal = $attributes['discount_val'] ?? 0;

    $subTotal = 0;
    $itemTaxTotal = 0;

    foreach ($lines as $line) {
        $subTotal += (int) round($line['price'] * $line['quantity']);
        $itemTaxTotal += array_sum(array_column($line['taxes'] ?? [], 'amount'));
    }

    $documentTaxTotal = array_sum(array_column($documentTaxes, 'amount'));
    $tax = $taxPerItem === 'YES' ? $itemTaxTotal : $documentTaxTotal;
    $total = $taxIncluded ? $subTotal - $discountVal : $subTotal - $discountVal + $tax;

    $invoice = Invoice::factory()->create(array_merge([
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'tax_included' => false,
        'discount' => 0,
        'discount_type' => 'fixed',
    ], $attributes, [
        'sub_total' => $subTotal,
        'discount_val' => $discountVal,
        'tax' => $tax,
        'total' => $total,
        'due_amount' => $total,
        'exchange_rate' => $rate,
        'base_sub_total' => (int) round($subTotal * $rate),
        'base_discount_val' => (int) round($discountVal * $rate),
        'base_tax' => (int) round($tax * $rate),
        'base_total' => (int) round($total * $rate),
        'base_due_amount' => (int) round($total * $rate),
    ]));

    foreach ($lines as $index => $line) {
        $amount = (int) round($line['price'] * $line['quantity']);
        $lineTax = array_sum(array_column($line['taxes'] ?? [], 'amount'));

        $item = $invoice->items()->create([
            'name' => $line['name'] ?? 'Line '.($index + 1),
            'quantity' => $line['quantity'],
            'price' => $line['price'],
            'discount_type' => 'fixed',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => $lineTax,
            'total' => $amount,
            'company_id' => $invoice->company_id,
            'exchange_rate' => $rate,
            'base_price' => (int) round($line['price'] * $rate),
            'base_discount_val' => 0,
            'base_tax' => (int) round($lineTax * $rate),
            'base_total' => (int) round($amount * $rate),
        ]);

        foreach ($line['taxes'] ?? [] as $taxRow) {
            creditableTax($invoice, $taxRow, ['invoice_item_id' => $item->id]);
        }
    }

    foreach ($documentTaxes as $taxRow) {
        creditableTax($invoice, $taxRow, ['invoice_id' => $invoice->id]);
    }

    return $invoice->fresh();
}

function creditableTax(Invoice $invoice, array $tax, array $owner): Tax
{
    return Tax::factory()->create(array_merge($owner, [
        'company_id' => $invoice->company_id,
        'amount' => $tax['amount'],
        'base_amount' => (int) round($tax['amount'] * $invoice->exchange_rate),
        'percent' => $tax['percent'] ?? 0,
        'exchange_rate' => $invoice->exchange_rate,
    ]));
}

/**
 * Record a payment against an invoice and settle its balance the way the
 * payment flow would.
 */
function creditablePayment(Invoice $invoice, int $amount): Payment
{
    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => $amount,
    ]);

    $due = (int) $invoice->due_amount - $amount;

    $invoice->due_amount = $due;
    $invoice->base_due_amount = (int) round($due * $invoice->exchange_rate);
    $invoice->paid_status = $due === 0 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;
    $invoice->save();

    return $payment;
}

/**
 * The ids of an invoice's line items, in creation order.
 */
function creditableItemIds(Invoice $invoice): array
{
    return $invoice->items()->orderBy('id')->pluck('id')->all();
}

test('creates a credit note from an invoice with negated totals', function () {
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]]);

    $response = postJson("api/v1/invoices/{$invoice->id}/credit-note");

    $response->assertStatus(201);

    $creditNoteId = $response->json('data.id');

    $this->assertDatabaseHas('invoices', [
        'id' => $creditNoteId,
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'related_invoice_id' => $invoice->id,
    ]);

    $creditNote = Invoice::find($creditNoteId);

    // Money stays integer cents and is negated.
    expect($creditNote->total)->toBe(-10000);
    expect($creditNote->sub_total)->toBe(-10000);
    // creator_id is set from the authenticated user (issue #7 from PR #536).
    expect($creditNote->creator_id)->toBe(1);
    // The credit note gets its own document number, distinct from the source.
    expect($creditNote->invoice_number)->not->toBe($invoice->invoice_number);
});

test('negates the line item amounts of the source invoice', function () {
    $invoice = creditableInvoice([['price' => 5000, 'quantity' => 2]]);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $item = Invoice::find($creditNoteId)->items->first();

    // Unit price and computed total are negative; amounts remain integer cents.
    expect($item->price)->toBe(-5000);
    expect($item->total)->toBe(-10000);
    expect($item->base_price)->toBeLessThan(0);
    // Every credit-note line names the invoice line it credits.
    expect((int) $item->source_invoice_item_id)->toBe($invoice->items->first()->id);
    // The quantity itself stays positive: the negative price is what makes the
    // line a credit.
    expect((float) $item->quantity)->toBe(2.0);
});

test('an empty request body reverses the whole invoice to the cent', function () {
    $invoice = creditableInvoice(
        [['price' => 2500, 'quantity' => 4]],
        ['discount_val' => 1000],
        [['amount' => 630, 'percent' => 7]]
    );

    expect($invoice->total)->toBe(9630);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::with('items', 'taxes')->find($creditNoteId);

    // Field for field the negation of the invoice, which is what a full
    // reversal has always produced and must keep producing.
    expect($creditNote->sub_total)->toBe(-10000)
        ->and($creditNote->discount_val)->toBe(-1000)
        ->and($creditNote->tax)->toBe(-630)
        ->and($creditNote->total)->toBe(-9630)
        ->and((int) $creditNote->base_sub_total)->toBe(-10000)
        ->and((int) $creditNote->base_discount_val)->toBe(-1000)
        ->and((int) $creditNote->base_tax)->toBe(-630)
        ->and((int) $creditNote->base_total)->toBe(-9630);

    $item = $creditNote->items->first();

    expect($item->price)->toBe(-2500)
        ->and($item->total)->toBe(-10000)
        ->and((int) $item->base_total)->toBe(-10000)
        ->and((float) $item->quantity)->toBe(4.0)
        ->and((int) $item->source_invoice_item_id)->toBe($invoice->items->first()->id);

    expect((int) $creditNote->taxes->first()->amount)->toBe(-630);
});

test('credits a single line of a three line invoice', function () {
    $invoice = creditableInvoice(
        [
            ['price' => 1000, 'quantity' => 1],
            ['price' => 1000, 'quantity' => 1],
            ['price' => 1000, 'quantity' => 1],
        ],
        ['discount_val' => 300],
        [['amount' => 189, 'percent' => 7]]
    );

    expect($invoice->total)->toBe(2889);

    [$first] = creditableItemIds($invoice);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::with('items')->find($creditNoteId);

    // One third of the lines credited, so one third of the document-level
    // discount and tax come back with it.
    expect($creditNote->sub_total)->toBe(-1000)
        ->and($creditNote->discount_val)->toBe(-100)
        ->and($creditNote->tax)->toBe(-63)
        ->and($creditNote->total)->toBe(-963)
        ->and((int) $creditNote->base_total)->toBe(-963);

    expect($creditNote->items)->toHaveCount(1);
    expect((int) $creditNote->items->first()->source_invoice_item_id)->toBe($first);

    $invoice->refresh();

    // The balance drops by exactly the credited amount and no more.
    expect((int) $invoice->due_amount)->toBe(1926)
        ->and((int) $invoice->base_due_amount)->toBe(1926)
        // A credit is not a payment: nothing was paid, so the invoice is still
        // unpaid, just for less.
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT);
});

test('a second credit note credits the remaining quantity', function () {
    $invoice = creditableInvoice(
        [
            ['price' => 1000, 'quantity' => 1],
            ['price' => 1000, 'quantity' => 1],
            ['price' => 1000, 'quantity' => 1],
        ],
        ['discount_val' => 300],
        [['amount' => 189, 'percent' => 7]]
    );

    [$first, $second, $third] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1]],
    ])->assertStatus(201);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [
            ['id' => $second, 'quantity' => 1],
            ['id' => $third, 'quantity' => 1],
        ],
    ])->assertStatus(201);

    // Telescoping: the chain of credits sums to exactly the invoice, to the
    // cent, in every field.
    expect((int) $invoice->creditNotes()->sum('total'))->toBe(-$invoice->total)
        ->and((int) $invoice->creditNotes()->sum('sub_total'))->toBe(-$invoice->sub_total)
        ->and((int) $invoice->creditNotes()->sum('tax'))->toBe(-$invoice->tax)
        ->and((int) $invoice->creditNotes()->sum('discount_val'))->toBe(-$invoice->discount_val);

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(0)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_COMPLETED);

    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.credited_status', 'FULL')
        ->assertJsonPath('data.credited_total', 2889);
});

test('a credit note may not exceed the unpaid balance of the invoice', function () {
    // 100 units at 1.00 each: crediting n units credits exactly n cents.
    $invoice = creditableInvoice([['price' => 100, 'quantity' => 100]]);

    creditablePayment($invoice, 4000);

    [$line] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 50]],
    ])->assertStatus(201);

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(1000)
        // Money was received, so the invoice stays partially paid even though
        // part of it was credited away.
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);

    // One cent past the unpaid balance: the invoice would end up owing the
    // customer money it was never paid.
    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 10.01]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'credit_amount_exceeds_invoice_balance');

    // Exactly the unpaid balance is fine.
    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 10]],
    ])->assertStatus(201);

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(0)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_COMPLETED);
});

test('a line cannot be credited beyond the quantity that was invoiced', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 3]]);

    [$line] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 4]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'credit_quantity_exceeds_remaining');

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 2]],
    ])->assertStatus(201);

    // Two of the three units are gone, so only one is still creditable.
    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 2]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'credit_quantity_exceeds_remaining');

    expect($invoice->creditNotes()->count())->toBe(1);
});

test('cannot credit a line that belongs to another invoice', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 1]]);
    $other = creditableInvoice([['price' => 1000, 'quantity' => 1]]);

    [$foreign] = creditableItemIds($other);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $foreign, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.id']);

    expect($invoice->creditNotes()->count())->toBe(0);
});

test('a credit note must credit something', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 1]]);

    [$line] = creditableItemIds($invoice);

    // Quantities are carried in hundredths, so anything below half a hundredth
    // credits nothing at all and must not mint an empty document.
    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 0.001]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'credit_note_must_credit_something');

    // A zero or negative quantity does not even reach the service.
    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 0]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect($invoice->creditNotes()->count())->toBe(0);
});

test('a fully credited invoice cannot be credited again', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 2]]);

    [$line] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'invoice_already_fully_credited');

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.invoice.0', 'invoice_already_fully_credited');

    expect($invoice->creditNotes()->count())->toBe(1);
});

test('stores the reason a credit note was issued and returns it', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 1]]);

    $response = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'reason' => 'Goods returned damaged',
    ])->assertStatus(201);

    $response->assertJsonPath('data.credit_reason', 'Goods returned damaged');

    expect(Invoice::find($response->json('data.id'))->credit_reason)
        ->toBe('Goods returned damaged');
});

test('the credit reason cannot be set through the invoice endpoints', function () {
    $payload = Invoice::factory()->raw([
        'credit_reason' => 'Written by a client',
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);

    $created = Invoice::find(postJson('api/v1/invoices', $payload)->assertOk()->json('data.id'));

    // The reason belongs to the credit-note flow; the invoice form must not be
    // able to write it.
    expect($created->credit_reason)->toBeNull();

    putJson("api/v1/invoices/{$created->id}", array_merge($payload, [
        'invoice_number' => $payload['invoice_number'].'-B',
        'credit_reason' => 'Written by a client',
    ]))->assertOk();

    expect($created->fresh()->credit_reason)->toBeNull();
});

test('a credited invoice can no longer be edited', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 2]]);

    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.allow_edit', true);

    [$line] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 1]],
    ])->assertStatus(201);

    // The credit note's lines are anchored to this invoice's item ids, so the
    // invoice is frozen from the first credit note on, partial or not.
    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.allow_edit', false);

    $payload = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);

    putJson("api/v1/invoices/{$invoice->id}", $payload)->assertStatus(403);
});

test('exposes how much of an invoice and of each line has been credited', function () {
    $invoice = creditableInvoice([
        ['price' => 1000, 'quantity' => 2],
        ['price' => 500, 'quantity' => 4],
    ]);

    [$first] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1.5]],
    ])->assertStatus(201);

    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.credited_total', 1500)
        ->assertJsonPath('data.credited_status', 'PARTIAL')
        ->assertJsonPath("data.credited_quantities.{$first}", 1.5);

    // The list carries the totals for the badge, but not the per-line
    // quantities: those need the credit notes' items and the list does not pay
    // for them.
    $row = collect(getJson("api/v1/invoices?invoice_id={$invoice->id}")->assertOk()->json('data'))
        ->firstWhere('id', $invoice->id);

    expect($row['credited_total'])->toBe(1500)
        ->and($row['credited_status'])->toBe('PARTIAL')
        ->and($row)->not->toHaveKey('credited_quantities');
});

test('reports an uncredited invoice as uncredited', function () {
    $invoice = creditableInvoice([['price' => 1000, 'quantity' => 1]]);

    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.credited_total', 0)
        ->assertJsonPath('data.credited_status', 'NONE')
        ->assertJsonPath('data.credit_reason', null)
        ->assertJsonPath('data.allow_edit', true);
});

test('pro-rates per item taxes and writes no document level tax', function () {
    $invoice = creditableInvoice(
        [['price' => 1000, 'quantity' => 2, 'taxes' => [['amount' => 140, 'percent' => 7]]]],
        ['tax_per_item' => 'YES']
    );

    expect($invoice->total)->toBe(2140);

    [$line] = creditableItemIds($invoice);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::with('items.taxes', 'taxes')->find($creditNoteId);

    expect($creditNote->sub_total)->toBe(-1000)
        ->and($creditNote->tax)->toBe(-70)
        ->and($creditNote->total)->toBe(-1070);

    $item = $creditNote->items->first();

    expect($item->tax)->toBe(-70)
        ->and($item->taxes)->toHaveCount(1)
        ->and((int) $item->taxes->first()->amount)->toBe(-70)
        ->and((int) $item->taxes->first()->base_amount)->toBe(-70)
        // The descriptive fields travel with the amount so the credit note can
        // be read on its own.
        ->and((float) $item->taxes->first()->percent)->toBe(7.0)
        ->and($item->taxes->first()->tax_type_id)->toBe($invoice->items->first()->taxes->first()->tax_type_id);

    // Per-item tax means no document-level tax row exists to credit.
    expect($creditNote->taxes)->toHaveCount(0);
});

test('follows the tax inclusive total when crediting part of an invoice', function () {
    $invoice = creditableInvoice(
        [['price' => 1000, 'quantity' => 2]],
        ['tax_included' => true],
        [['amount' => 140, 'percent' => 7]]
    );

    // Tax included: the total is the sub total, the tax is already inside it.
    expect($invoice->total)->toBe(2000);

    [$line] = creditableItemIds($invoice);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    expect($creditNote->sub_total)->toBe(-1000)
        ->and($creditNote->tax)->toBe(-70)
        // Not -1070: the credited tax is inside the credited total.
        ->and($creditNote->total)->toBe(-1000);
});

test('pro-rates the base amounts of a foreign currency invoice and telescopes exactly', function () {
    $invoice = creditableInvoice(
        [['price' => 1000, 'quantity' => 3]],
        ['exchange_rate' => 1.37],
        [['amount' => 210, 'percent' => 7]]
    );

    expect($invoice->total)->toBe(3210)
        ->and((int) $invoice->base_total)->toBe(4398)
        ->and((int) $invoice->base_tax)->toBe(288);

    [$line] = creditableItemIds($invoice);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    // Pro-rated from the STORED base amounts, not recomputed through the rate:
    // 4398 / 3 is 1466, while round(1070 * 1.37) would be 1466 by luck and
    // round(70 * 1.37) would be 96 here but not everywhere.
    expect($creditNote->total)->toBe(-1070)
        ->and((int) $creditNote->base_sub_total)->toBe(-1370)
        ->and((int) $creditNote->base_tax)->toBe(-96)
        ->and((int) $creditNote->base_total)->toBe(-1466);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 2]],
    ])->assertStatus(201);

    // Two chunks, and the books balance to the cent in the company currency
    // just as they do in the document currency.
    expect((int) $invoice->creditNotes()->sum('total'))->toBe(-3210)
        ->and((int) $invoice->creditNotes()->sum('base_total'))->toBe(-4398)
        ->and((int) $invoice->creditNotes()->sum('base_tax'))->toBe(-288)
        ->and((int) $invoice->creditNotes()->sum('base_sub_total'))->toBe(-4110);

    expect((int) $invoice->fresh()->due_amount)->toBe(0);
});

test('sets the related invoice relationship on the credit note', function () {
    $invoice = creditableInvoice();

    $response = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201);

    $creditNote = Invoice::find($response->json('data.id'));

    expect($creditNote->relatedInvoice->id)->toBe($invoice->id);
    expect($invoice->fresh()->creditNotes->pluck('id'))->toContain($creditNote->id);

    // The resource exposes the original invoice reference.
    $response->assertJsonPath('data.related_invoice.id', $invoice->id);
    $response->assertJsonPath('data.related_invoice.invoice_number', $invoice->invoice_number);
    $response->assertJsonPath('data.type', Invoice::TYPE_CREDIT_NOTE);
});

test('cannot create a credit note from another credit note', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // Reversing a credit note is a domain rule violation, not an auth failure.
    postJson("api/v1/invoices/{$creditNoteId}/credit-note")
        ->assertStatus(422);
});

test('cannot create a credit note for an invoice of another company', function () {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create(['company_id' => Company::factory()->create()->id]);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(403);
});

test('generates a pdf for a credit note', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $pdf = $creditNote->getPDFData();
    $output = $pdf->output();

    // A real PDF document was produced by the credit-note template.
    expect(substr($output, 0, 4))->toBe('%PDF');
});

test('settles the original invoice when a credit note is created', function () {
    $invoice = creditableInvoice();

    postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

    $invoice->refresh();

    // A full reversal nets the original invoice's balance to exactly zero, so
    // it drops out of every "awaiting payment" view (issue #317 community ask;
    // same behavior sevDesk applies and @gdarko praised in PR #536).
    expect((int) $invoice->due_amount)->toBe(0);
    expect((int) $invoice->base_due_amount)->toBe(0);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_PAID);
    expect($invoice->status)->toBe(Invoice::STATUS_COMPLETED);
});

test('the credit note itself is created settled but still a draft', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    // The credit note pairs with the original invoice and nothing is owed on
    // it, so it must never appear as an open (negative) balance anywhere.
    expect((int) $creditNote->due_amount)->toBe(0);
    expect((int) $creditNote->base_due_amount)->toBe(0);
    expect($creditNote->paid_status)->toBe(Invoice::STATUS_PAID);
    // A reversal is never owed, so it carries no due date at all.
    expect($creditNote->due_date)->toBeNull();
    // Settled is not the same as finished: the credit note still has to be
    // reviewed and emailed, so it is born DRAFT and gets the ordinary Send
    // affordances. send() promotes it to SENT.
    expect($creditNote->status)->toBe(Invoice::STATUS_DRAFT);
    // Totals stay fully negated, though.
    expect($creditNote->total)->toBe(-10000);
});

test('the original invoice exposes its credit notes for the UI banner', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNoteNumber = Invoice::find($creditNoteId)->invoice_number;

    // Mirror of the credit note's related_invoice back-link: the original
    // invoice must reference the storno document ("Storniert via ST-XXXX").
    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.credit_notes.0.id', $creditNoteId)
        ->assertJsonPath('data.credit_notes.0.invoice_number', $creditNoteNumber);
});

test('deleting a credit note restores the original invoice balance', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    expect((int) $invoice->fresh()->due_amount)->toBe(0);

    postJson('api/v1/invoices/delete', ['ids' => [$creditNoteId]])
        ->assertOk()
        ->assertJson(['success' => true]);

    $invoice->refresh();

    // Mirror of the create-side adjustment (PR #536's delete reversal).
    expect((int) $invoice->due_amount)->toBe(10000);
    expect((int) $invoice->base_due_amount)->toBe(10000);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID);
    expect($invoice->status)->toBe(Invoice::STATUS_SENT);
});

test('deleting a credit note restores a partially paid balance from payments', function () {
    $invoice = creditableInvoice([['price' => 100, 'quantity' => 100]]);

    creditablePayment($invoice, 4000);

    [$line] = creditableItemIds($invoice);

    // Crediting the whole unpaid balance settles the invoice.
    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 60]],
    ])->assertStatus(201)->json('data.id');

    expect((int) $invoice->fresh()->due_amount)->toBe(0);

    postJson('api/v1/invoices/delete', ['ids' => [$creditNoteId]])
        ->assertOk();

    $invoice->refresh();

    // due = total - recorded payments - surviving credit notes, never a stale
    // pre-storno snapshot.
    expect((int) $invoice->due_amount)->toBe(6000);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

test('deleting one of two credit notes gives back only that credit', function () {
    $invoice = creditableInvoice([['price' => 100, 'quantity' => 100]]);

    creditablePayment($invoice, 1000);

    [$line] = creditableItemIds($invoice);

    $first = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 20]],
    ])->assertStatus(201)->json('data.id');

    $second = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 30]],
    ])->assertStatus(201)->json('data.id');

    expect((int) $invoice->fresh()->due_amount)->toBe(4000);

    postJson('api/v1/invoices/delete', ['ids' => [$first]])->assertOk();

    // 10000 - 1000 paid - 3000 still credited.
    expect((int) $invoice->fresh()->due_amount)->toBe(6000);

    postJson('api/v1/invoices/delete', ['ids' => [$second]])->assertOk();

    expect((int) $invoice->fresh()->due_amount)->toBe(9000);
    expect($invoice->fresh()->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

test('deleting two credit notes of one invoice in a single request settles it once', function () {
    $invoice = creditableInvoice([['price' => 100, 'quantity' => 100]]);

    [$line] = creditableItemIds($invoice);

    $first = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 20]],
    ])->assertStatus(201)->json('data.id');

    $second = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $line, 'quantity' => 30]],
    ])->assertStatus(201)->json('data.id');

    postJson('api/v1/invoices/delete', ['ids' => [$first, $second]])->assertOk();

    $invoice->refresh();

    expect((int) $invoice->due_amount)->toBe(10000)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->status)->toBe(Invoice::STATUS_SENT);
});

test('deleting the original invoice and its credit note together succeeds', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    postJson('api/v1/invoices/delete', ['ids' => [$invoice->id, $creditNoteId]])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    $this->assertDatabaseMissing('invoices', ['id' => $creditNoteId]);
});

test('cannot delete an invoice while a credit note still reverses it', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // Deleting only the original would leave the credit note pointing at a row
    // that no longer exists.
    postJson('api/v1/invoices/delete', ['ids' => [$invoice->id]])
        ->assertStatus(422);

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    $this->assertDatabaseHas('invoices', ['id' => $creditNoteId]);
});

test('no surviving row keeps a dangling related invoice reference', function () {
    $invoice = creditableInvoice();

    $creditNote = app(CreditNoteService::class)->create($invoice, [], null);

    // There is no DB foreign key, so the cascade is the service's job. Deleting
    // the original directly (the request layer blocks this) must still not
    // leave the credit note pointing at a missing invoice.
    app(InvoiceService::class)->delete(collect([$invoice->id]));

    expect(Invoice::find($creditNote->id)->related_invoice_id)->toBeNull();
});

test('completing an uncredited invoice still zeroes its balance', function () {
    $invoice = creditableInvoice();

    postJson("api/v1/invoices/{$invoice->id}/status", ['status' => Invoice::STATUS_COMPLETED])
        ->assertOk();

    $invoice->refresh();

    // The credit-note bookkeeping must not touch the manual status change.
    expect((int) $invoice->due_amount)->toBe(0)
        ->and($invoice->status)->toBe(Invoice::STATUS_COMPLETED)
        ->and($invoice->paid_status)->toBe(Invoice::STATUS_PAID);
});

test('renders a credit note pdf through the original invoice template family, not a hardcoded layout', function () {
    // Regression for: credit notes always rendered through one hardcoded
    // generic layout regardless of which of the 3 invoice templates the
    // company actually uses. invoice2 has a distinctive purple header
    // markup ("header-section-right") that the old standalone
    // credit-note.blade.php never contained.
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]], ['template_name' => 'invoice2']);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$creditNote->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('header-section-right', false);
    $response->assertSee('Credit Note');
    $response->assertSee($invoice->invoice_number);
});

test('renders a credit note pdf under the invoice3 template family', function () {
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]], ['template_name' => 'invoice3']);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$creditNote->unique_hash}?preview=1");

    $response->assertOk();
    // "main-content" is a structural marker unique to invoice3.blade.php.
    $response->assertSee('main-content', false);
    $response->assertSee('Credit Note');
});

test('shows a cancellation banner on the original invoice pdf under a non-default template', function () {
    // Regression for: the actual generated/printed/emailed PDF of a
    // cancelled invoice showed zero indication it had been reversed by a
    // credit note (only the Vue UI banner existed).
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]], ['template_name' => 'invoice3']);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Cancelled');
    $response->assertSee($creditNote->invoice_number);
});

test('shows a cancellation banner on the original invoice pdf under the default template', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Cancelled');
    $response->assertSee($creditNote->invoice_number);
});

test('prints the credit reason on the credit note pdf and escapes it', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'reason' => 'Goods returned <b>damaged</b>',
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$creditNote->unique_hash}?preview=1");

    $response->assertOk();
    // assertSee escapes by default, so this is the escaped rendering.
    $response->assertSee('Reason: Goods returned <b>damaged</b>');
    // The operator's text is data, never markup: the raw tags must not reach
    // the document, where Chromium would happily render them as bold.
    $response->assertDontSee('Goods returned <b>damaged</b>', false);
});

test('omits the reason line from a credit note pdf that has no reason', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    get("/invoices/pdf/{$creditNote->unique_hash}?preview=1")
        ->assertOk()
        ->assertDontSee('Reason:');
});

test('shows a partially credited banner naming the amount and the credit note', function () {
    $invoice = creditableInvoice([
        ['price' => 1000, 'quantity' => 1],
        ['price' => 1000, 'quantity' => 1],
    ]);

    [$first] = creditableItemIds($invoice);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Partially Credited');
    $response->assertSee($creditNote->invoice_number);
    $response->assertSee(format_money_pdf(1000, $invoice->customer->currency), false);
    // Half an invoice is not a cancelled invoice.
    $response->assertDontSee('Cancelled via credit note');
});

test('lists every credit note on the cancelled banner once the invoice is fully credited', function () {
    $invoice = creditableInvoice([
        ['price' => 1000, 'quantity' => 1],
        ['price' => 1000, 'quantity' => 1],
    ]);

    [$first, $second] = creditableItemIds($invoice);

    $firstNote = Invoice::find(
        postJson("api/v1/invoices/{$invoice->id}/credit-note", [
            'items' => [['id' => $first, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id')
    );

    $secondNote = Invoice::find(
        postJson("api/v1/invoices/{$invoice->id}/credit-note", [
            'items' => [['id' => $second, 'quantity' => 1]],
        ])->assertStatus(201)->json('data.id')
    );

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Cancelled');
    // Naming only the first credit note would leave the reader unable to tie
    // the reversal to the documents that produced it.
    $response->assertSee($firstNote->invoice_number);
    $response->assertSee($secondNote->invoice_number);
    $response->assertDontSee('Partially Credited');
});

test('a partially credited invoice pdf reports a credit, not a payment', function () {
    // The hazard this pins: crediting an invoice moves its balance, so a totals
    // block driven by paid_status alone announces "Amount Paid" for money that
    // was never received.
    $invoice = creditableInvoice([
        ['price' => 1000, 'quantity' => 1],
        ['price' => 1000, 'quantity' => 1],
    ]);

    [$first] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1]],
    ])->assertStatus(201);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Amount Credited');
    $response->assertSee('Amount Due');
    $response->assertDontSee('Amount Paid');
});

test('an invoice both paid and credited pdf reports the two separately', function () {
    $invoice = creditableInvoice([
        ['price' => 1000, 'quantity' => 1],
        ['price' => 1000, 'quantity' => 1],
    ]);

    creditablePayment($invoice, 500);

    [$first] = creditableItemIds($invoice);

    postJson("api/v1/invoices/{$invoice->id}/credit-note", [
        'items' => [['id' => $first, 'quantity' => 1]],
    ])->assertStatus(201);

    $invoice->refresh();

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Amount Credited');
    $response->assertSee('Amount Paid');
    $response->assertSee(format_money_pdf(1000, $invoice->customer->currency), false);
    $response->assertSee(format_money_pdf(500, $invoice->customer->currency), false);
});

test('an ordinary partially paid invoice pdf still shows the amount paid', function () {
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]]);

    creditablePayment($invoice, 4000);

    $invoice->refresh();

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Amount Paid');
    $response->assertSee('Amount Due');
    $response->assertDontSee('Amount Credited');
    $response->assertSee(format_money_pdf(4000, $invoice->customer->currency), false);
    $response->assertSee(format_money_pdf(6000, $invoice->customer->currency), false);
});

test('an unpaid invoice pdf shows neither a paid nor a credited row', function () {
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]]);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertDontSee('Amount Paid');
    $response->assertDontSee('Amount Credited');
    $response->assertDontSee('Amount Due');
});

test('a credit note pdf shows no amount paid row', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$creditNote->unique_hash}?preview=1");

    $response->assertOk();
    // A credit note settles nothing: its own totals block is the negated
    // document, and a paid line there would be read as a refund.
    $response->assertDontSee('Amount Paid');
    $response->assertDontSee('Amount Credited');
});

test('every credit note phrase is translated in all five maintained locales', function () {
    $locales = ['en', 'de', 'fr', 'it', 'mk'];

    $catalogues = [];

    foreach ($locales as $locale) {
        $catalogues[$locale] = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
    }

    $english = $catalogues['en'];

    $expected = [];

    foreach (array_keys($english['invoices']) as $key) {
        if (str_contains($key, 'credit')) {
            $expected[] = ['invoices', $key];
        }
    }

    foreach (array_keys($english['errors']) as $key) {
        if (str_starts_with($key, 'credit_') || $key === 'invoice_already_fully_credited') {
            $expected[] = ['errors', $key];
        }
    }

    foreach (array_keys($english) as $key) {
        if (str_starts_with($key, 'pdf_') && (str_contains($key, 'credit') || str_contains($key, 'cancelled'))) {
            $expected[] = [null, $key];
        }
    }

    expect($expected)->not->toBeEmpty();

    $missing = [];

    foreach ($expected as [$section, $key]) {
        foreach ($locales as $locale) {
            $bag = $section === null ? $catalogues[$locale] : ($catalogues[$locale][$section] ?? []);

            if (! array_key_exists($key, $bag)) {
                $missing[] = $locale.': '.($section === null ? $key : $section.'.'.$key);
            }
        }
    }

    expect($missing)->toBe([]);

    // Guards that partial crediting removed: one credit note per invoice, and
    // no crediting an invoice with payments. A stale string in any catalogue
    // would still be shown by a translated install.
    $retired = [
        ['invoices', 'confirm_create_credit_note'],
        ['errors', 'invoice_already_has_credit_note'],
        ['errors', 'invoice_with_payments_cannot_be_credited'],
    ];

    $leftovers = [];

    foreach ($retired as [$section, $key]) {
        foreach ($locales as $locale) {
            if (array_key_exists($key, $catalogues[$locale][$section] ?? [])) {
                $leftovers[] = $locale.': '.$section.'.'.$key;
            }
        }
    }

    expect($leftovers)->toBe([]);
});

test('sends a credit note to the customer through the normal send endpoint', function () {
    Mail::fake();

    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $data = [
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
        'subject' => 'Your credit note',
        'body' => 'Please find your credit note attached.',
    ];

    // There is no separate credit-note send endpoint: a credit note goes out
    // through the invoice send channel, which picks the mailable by type.
    postJson("api/v1/invoices/{$creditNoteId}/send", $data)
        ->assertOk()
        ->assertJson(['success' => true]);

    Mail::assertSent(SendCreditNoteMail::class);
    Mail::assertNotSent(SendInvoiceMail::class);

    // Sending promotes the draft credit note the same way it promotes an
    // invoice.
    $creditNote = Invoice::find($creditNoteId);
    expect($creditNote->status)->toBe(Invoice::STATUS_SENT);
    expect((bool) $creditNote->sent)->toBeTrue();
});

test('sending a regular invoice still uses the invoice mailable', function () {
    Mail::fake();

    $invoice = Invoice::factory()->hasItems(1)->create();

    postJson("api/v1/invoices/{$invoice->id}/send", [
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
        'subject' => 'Your invoice',
        'body' => 'Please find your invoice attached.',
    ])->assertOk();

    Mail::assertSent(SendInvoiceMail::class);
    Mail::assertNotSent(SendCreditNoteMail::class);
});

test('previews the credit note email template, not the invoice one', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // The two templates render near-identical markup, so the assertion hooks
    // the view that actually gets composed rather than its output.
    $rendered = [];
    View::composer(['emails.send.credit-note', 'emails.send.invoice'], function ($view) use (&$rendered) {
        $rendered[] = $view->name();
    });

    getJson("api/v1/invoices/{$creditNoteId}/send/preview?".http_build_query([
        'subject' => 'Your credit note',
        'body' => 'Please find your credit note attached.',
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
    ]))->assertOk();

    expect($rendered)->toContain('emails.send.credit-note');
    expect($rendered)->not->toContain('emails.send.invoice');
});

test('a credit note cannot be edited', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $payload = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);

    // A reversal document is immutable: editing it would recompute its totals
    // positive through the ordinary invoice payload.
    putJson("api/v1/invoices/{$creditNoteId}", $payload)->assertStatus(403);
});

test('a client cannot mint a credit note through the invoice create endpoint', function () {
    $payload = Invoice::factory()->raw([
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'related_invoice_id' => 1,
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);

    $response = postJson('api/v1/invoices', $payload)->assertOk();

    // Credit notes are minted only by CreditNoteService::create(); the request
    // payload must not be able to declare one.
    $created = Invoice::find($response->json('data.id'));

    expect($created->type)->toBe(Invoice::TYPE_INVOICE);
    expect($created->related_invoice_id)->toBeNull();
});

test('cannot credit a draft invoice', function () {
    $invoice = creditableInvoice([['price' => 10000, 'quantity' => 1]], [
        'status' => Invoice::STATUS_DRAFT,
        'sent' => false,
    ]);

    // A draft was never issued, so there is nothing to reverse.
    postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(422);

    expect($invoice->creditNotes()->count())->toBe(0);
});

test('a credit note cannot be cloned or converted to an estimate', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // Both copy the amounts unnegated, so either would mint a positive
    // document out of a reversal.
    postJson("api/v1/invoices/{$creditNoteId}/clone")->assertStatus(422);
    postJson("api/v1/invoices/{$creditNoteId}/convert-to-estimate")->assertStatus(422);
});

test('a credit note is never marked overdue by the status command', function () {
    $invoice = creditableInvoice();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // Force the credit note into the shape the command looks for: sent, not
    // completed, with a due date in the past.
    Invoice::where('id', $creditNoteId)->update([
        'status' => Invoice::STATUS_SENT,
        'due_date' => now()->subMonth()->format('Y-m-d'),
    ]);

    Artisan::call('check:invoices:status');

    expect((bool) Invoice::find($creditNoteId)->overdue)->toBeFalse();
});

test('a real invoice is still marked overdue by the status command', function () {
    $invoice = Invoice::factory()->hasItems(1)->create([
        'status' => Invoice::STATUS_SENT,
        'due_date' => now()->subMonth()->format('Y-m-d'),
        'overdue' => false,
    ]);

    Artisan::call('check:invoices:status');

    expect((bool) $invoice->fresh()->overdue)->toBeTrue();
});

describe('credit note numbering', function () {
    test('numbers credit notes in their own sequence, independent of invoices', function () {
        $first = creditableInvoice();
        $second = creditableInvoice();

        expect($first->invoice_number)->toBe('INV-000001');
        expect($first->sequence_number)->toBe(1);
        expect($second->invoice_number)->toBe('INV-000002');
        expect($second->sequence_number)->toBe(2);

        $firstCreditNote = Invoice::find(
            postJson("api/v1/invoices/{$first->id}/credit-note")
                ->assertStatus(201)
                ->json('data.id')
        );

        $secondCreditNote = Invoice::find(
            postJson("api/v1/invoices/{$second->id}/credit-note")
                ->assertStatus(201)
                ->json('data.id')
        );

        // Credit notes live in the invoices table but count from 1 on their own
        // format, so the two document series never interleave.
        expect($firstCreditNote->invoice_number)->toBe('CN-000001');
        expect($firstCreditNote->sequence_number)->toBe(1);
        expect($secondCreditNote->invoice_number)->toBe('CN-000002');
        expect($secondCreditNote->sequence_number)->toBe(2);

        // And the invoice sequence is untouched by the two credit notes: the
        // next invoice is 3, not 5.
        $third = creditableInvoice();

        expect($third->invoice_number)->toBe('INV-000003');
        expect($third->sequence_number)->toBe(3);
    });

    test('generates the credit note number from the credit_note_number_format setting', function () {
        $companyId = User::find(1)->companies()->first()->id;

        CompanySetting::setSettings([
            'credit_note_number_format' => '{{SERIES:STORNO}}{{DELIMITER:/}}{{SEQUENCE:4}}',
        ], $companyId);

        $invoice = creditableInvoice();

        $creditNote = Invoice::find(
            postJson("api/v1/invoices/{$invoice->id}/credit-note")
                ->assertStatus(201)
                ->json('data.id')
        );

        expect($creditNote->invoice_number)->toBe('STORNO/0001');
    });

    test('returns the next credit note number from the next-number endpoint', function () {
        getJson('api/v1/next-number?key=credit_note')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'nextNumber' => 'CN-000001',
            ]);

        $invoice = creditableInvoice();

        postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

        // The preview advances with the credit note sequence, not the invoice one.
        getJson('api/v1/next-number?key=credit_note')
            ->assertStatus(200)
            ->assertJson([
                'nextNumber' => 'CN-000002',
            ]);
    });
});
