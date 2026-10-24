<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Contracts\Host\InvoiceDocumentReader;
use InvoiceShelf\Modules\Documents\Data\InvoiceDocument;
use Tests\Support\InvoiceDocumentScenario;

/**
 * Module API 1.5: modules read whole invoices through InvoiceDocumentReader.
 * The golden file is the contract's worked example; the e-invoicing module
 * builds its fixtures from copies of it and its CI fails when they drift.
 * Run with UPDATE_GOLDEN=1 to rewrite it after a deliberate change.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->company = User::find(1)->companies()->first();
    $this->invoice = InvoiceDocumentScenario::seed($this->company);
    $this->reader = app(InvoiceDocumentReader::class);
});

/**
 * The document as an array with its database ids replaced by stable ones, so
 * the golden file does not depend on what else the test database holds.
 *
 * @return array<string, mixed>
 */
function stableDocument(InvoiceDocument $document): array
{
    $data = $document->toArray();
    $data['id'] = 1;
    $data['company_id'] = 1;

    foreach ($data['lines'] as $index => $line) {
        $data['lines'][$index]['id'] = $index + 1;
    }

    foreach ($data['taxes'] as $index => $tax) {
        $data['taxes'][$index]['tax_type_id'] = 1;
    }

    return $data;
}

test('an invoice reads as the golden document', function () {
    $path = base_path('tests/Fixtures/modules/invoice-document.standard.json');
    $actual = stableDocument($this->reader->invoiceDocument($this->company->id, $this->invoice->id));

    if (getenv('UPDATE_GOLDEN')) {
        file_put_contents($path, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    expect($actual)->toBe(json_decode((string) file_get_contents($path), true));
});

test('the golden document round-trips through the sdk data classes', function () {
    $golden = json_decode((string) file_get_contents(base_path('tests/Fixtures/modules/invoice-document.standard.json')), true);

    expect(InvoiceDocument::fromArray($golden)->toArray())->toBe($golden);
});

test('another company reads the invoice as missing', function () {
    $other = Company::factory()->create();

    expect($this->reader->invoiceDocument($other->id, $this->invoice->id))->toBeNull()
        ->and($this->reader->invoiceDocument($this->company->id, 999999))->toBeNull();
});

test('a unit code is matched by unit name regardless of case, and an unknown unit has none', function () {
    $document = $this->reader->invoiceDocument($this->company->id, $this->invoice->id);

    expect($document->lines[0]->unitName)->toBe('hour')
        ->and($document->lines[0]->unitCode)->toBe('HUR')
        ->and($document->lines[1]->unitCode)->toBeNull();
});

test('notes are plain text with their placeholders filled', function () {
    expect($this->reader->invoiceDocument($this->company->id, $this->invoice->id)->notes)
        ->toBe('Thank you for your business, Muster GmbH.');
});

test('a credit note names the invoice it corrects and keeps its stored signs', function () {
    $creditNote = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->invoice->customer_id,
        'currency_id' => $this->invoice->currency_id,
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'related_invoice_id' => $this->invoice->id,
        'credit_reason' => 'Returned goods',
        'invoice_number' => 'CN-000001',
        'reference_number' => 'INV-000042',
        'sub_total' => -10000,
        'tax' => -1900,
        'total' => -11900,
        'due_amount' => 0,
    ]);

    $document = $this->reader->invoiceDocument($this->company->id, $creditNote->id);

    expect($document->isCreditNote())->toBeTrue()
        ->and($document->creditReason)->toBe('Returned goods')
        ->and($document->related?->number)->toBe('INV-000042')
        ->and($document->related?->date)->toBe('2026-02-01')
        ->and($document->total)->toBe(-11900);
});

test('the seller reads on its own, for checks made before any invoice exists', function () {
    $seller = $this->reader->seller($this->company->id);

    expect($seller->name)->toBe('Acme GmbH')
        ->and($seller->vatId)->toBe('DE123456789')
        ->and($seller->address?->countryCode)->toBe('DE')
        ->and($this->reader->seller(999999))->toBeNull();
});
