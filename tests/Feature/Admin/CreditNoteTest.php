<?php

use App\Mail\SendCreditNoteMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

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
    $invoice = Invoice::factory()->hasItems(1)->create();

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
    $invoice = Invoice::factory()->hasItems(1)->create();

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

test('sends a credit note to the customer by email', function () {
    Mail::fake();

    $invoice = Invoice::factory()->hasItems(1)->create();

    $creditNoteId = postJson("api/v1/invoices/{$invoice->id}/credit-note")
        ->assertStatus(201)
        ->json('data.id');

    $data = [
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
        'subject' => 'Your credit note',
        'body' => 'Please find your credit note attached.',
    ];

    postJson("api/v1/invoices/{$creditNoteId}/credit-note/send", $data)
        ->assertOk()
        ->assertJson(['success' => true]);

    Mail::assertSent(SendCreditNoteMail::class);
});
