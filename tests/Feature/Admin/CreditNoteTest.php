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

test('creates a credit note from an invoice with negated totals', function () {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1, ['price' => 5000, 'quantity' => 2, 'tax' => 0, 'discount_val' => 0])
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
            'discount_per_item' => 'NO',
            'tax_per_item' => 'NO',
        ]);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $item = Invoice::find($creditNoteId)->items->first();

    // Unit price and computed total are negative; amounts remain integer cents.
    expect($item->price)->toBe(-5000);
    expect($item->total)->toBe(-10000);
    expect($item->base_price)->toBeLessThan(0);
});

test('sets the related invoice relationship on the credit note', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sent' => true,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
            'base_due_amount' => 10000,
            'exchange_rate' => 1,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
            'exchange_rate' => 1,
        ]);

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
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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

test('cannot create a second credit note for the same invoice', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

    // The invoice is already fully reversed; a second full reversal would
    // double-negate the books. Domain rule violation => 422.
    postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(422);

    expect($invoice->creditNotes()->count())->toBe(1);
});

test('deleting a credit note restores the original invoice balance', function () {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sent' => true,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
            'base_due_amount' => 10000,
            'exchange_rate' => 1,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sent' => true,
            'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 6000,
            'base_due_amount' => 6000,
            'exchange_rate' => 1,
        ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 4000,
    ]);

    // The API refuses to credit an invoice that already took money, so the
    // credit note is minted through the service here. The restore path still
    // has to be exact for rows that reached this state another way (a payment
    // recorded against an already-credited invoice, or data from before the
    // guard existed).
    $creditNoteId = app(InvoiceService::class)->createCreditNote($invoice)->id;

    expect((int) $invoice->fresh()->due_amount)->toBe(0);

    postJson('api/v1/invoices/delete', ['ids' => [$creditNoteId]])
        ->assertOk();

    $invoice->refresh();

    // due = total - recorded payments, never a stale pre-storno snapshot.
    expect((int) $invoice->due_amount)->toBe(6000);
    expect($invoice->paid_status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
});

test('deleting the original invoice and its credit note together succeeds', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    postJson('api/v1/invoices/delete', ['ids' => [$invoice->id, $creditNoteId]])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    $this->assertDatabaseMissing('invoices', ['id' => $creditNoteId]);
});

test('renders a credit note pdf through the original invoice template family, not a hardcoded layout', function () {
    // Regression for: credit notes always rendered through one hardcoded
    // generic layout regardless of which of the 3 invoice templates the
    // company actually uses. invoice2 has a distinctive purple header
    // markup ("header-section-right") that the old standalone
    // credit-note.blade.php never contained.
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'template_name' => 'invoice2',
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'template_name' => 'invoice3',
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
        ]);

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
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'template_name' => 'invoice3',
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 10000,
        ]);

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
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $creditNote = Invoice::find($creditNoteId);

    $response = get("/invoices/pdf/{$invoice->unique_hash}?preview=1");

    $response->assertOk();
    $response->assertSee('Cancelled');
    $response->assertSee($creditNote->invoice_number);
});

test('sends a credit note to the customer through the normal send endpoint', function () {
    Mail::fake();

    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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

    // Credit notes are minted only by createCreditNote(); the request payload
    // must not be able to declare one.
    $created = Invoice::find($response->json('data.id'));

    expect($created->type)->toBe(Invoice::TYPE_INVOICE);
    expect($created->related_invoice_id)->toBeNull();
});

test('cannot credit an invoice that already has a payment', function () {
    $invoice = Invoice::factory()
        ->hasItems(1)
        ->create([
            'status' => Invoice::STATUS_SENT,
            'sub_total' => 10000,
            'total' => 10000,
            'tax' => 0,
            'discount_val' => 0,
            'due_amount' => 6000,
            'exchange_rate' => 1,
        ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'amount' => 4000,
    ]);

    postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(422);

    expect($invoice->creditNotes()->count())->toBe(0);
});

test('cannot credit a draft invoice', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_DRAFT]);

    // A draft was never issued, so there is nothing to reverse.
    postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(422);

    expect($invoice->creditNotes()->count())->toBe(0);
});

test('a credit note cannot be cloned or converted to an estimate', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    // Both copy the amounts unnegated, so either would mint a positive
    // document out of a reversal.
    postJson("api/v1/invoices/{$creditNoteId}/clone")->assertStatus(422);
    postJson("api/v1/invoices/{$creditNoteId}/convert-to-estimate")->assertStatus(422);
});

test('a credit note is never marked overdue by the status command', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
        $first = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);
        $second = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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
        $third = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

        expect($third->invoice_number)->toBe('INV-000003');
        expect($third->sequence_number)->toBe(3);
    });

    test('generates the credit note number from the credit_note_number_format setting', function () {
        $companyId = User::find(1)->companies()->first()->id;

        CompanySetting::setSettings([
            'credit_note_number_format' => '{{SERIES:STORNO}}{{DELIMITER:/}}{{SEQUENCE:4}}',
        ], $companyId);

        $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

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

        $invoice = Invoice::factory()->hasItems(1)->create(['status' => Invoice::STATUS_SENT]);

        postJson("api/v1/invoices/{$invoice->id}/credit-note")->assertStatus(201);

        // The preview advances with the credit note sequence, not the invoice one.
        getJson('api/v1/next-number?key=credit_note')
            ->assertStatus(200)
            ->assertJson([
                'nextNumber' => 'CN-000002',
            ]);
    });
});
