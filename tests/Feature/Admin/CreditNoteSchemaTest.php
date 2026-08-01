<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

/**
 * Resolve a column definition from the information schema so nullability can
 * be asserted without reaching for driver-specific SQL.
 */
function creditNoteColumn(string $table, string $column): array
{
    $definition = collect(Schema::getColumns($table))
        ->firstWhere('name', $column);

    expect($definition)->not->toBeNull();

    return $definition;
}

test('the credit note columns exist with the expected nullability', function () {
    expect(Schema::hasColumn('invoices', 'type'))->toBeTrue();
    expect(Schema::hasColumn('invoices', 'related_invoice_id'))->toBeTrue();
    expect(Schema::hasColumn('invoices', 'credit_reason'))->toBeTrue();
    expect(Schema::hasColumn('invoice_items', 'source_invoice_item_id'))->toBeTrue();

    // "type" is non-nullable and defaults to INVOICE so pre-existing rows fall
    // inside the type-scoped serial-number queries.
    expect(creditNoteColumn('invoices', 'type')['nullable'])->toBeFalse();

    expect(creditNoteColumn('invoices', 'related_invoice_id')['nullable'])->toBeTrue();
    expect(creditNoteColumn('invoices', 'credit_reason')['nullable'])->toBeTrue();
    expect(creditNoteColumn('invoice_items', 'source_invoice_item_id')['nullable'])->toBeTrue();
});

test('invoices created without a type default to INVOICE', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->fresh()->type)->toBe(Invoice::TYPE_INVOICE);
});

test('a tax row persists a negative base amount', function () {
    $tax = Tax::factory()->create([
        'amount' => -2500,
        'base_amount' => -2500,
    ]);

    $this->assertDatabaseHas('taxes', [
        'id' => $tax->id,
        'amount' => -2500,
        'base_amount' => -2500,
    ]);

    expect((int) $tax->fresh()->base_amount)->toBe(-2500);
});

test('an invoice item persists a negative price and links its source line', function () {
    $invoice = Invoice::factory()->hasItems(1)->create();
    $sourceItem = $invoice->items()->first();

    $creditLine = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'source_invoice_item_id' => $sourceItem->id,
        'price' => -1500,
        'base_price' => -1500,
        'total' => -1500,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'id' => $creditLine->id,
        'source_invoice_item_id' => $sourceItem->id,
        'price' => -1500,
        'base_price' => -1500,
    ]);
});

test('every seeded company has a credit note number format', function () {
    $companies = Company::all();

    expect($companies)->not->toBeEmpty();

    $companies->each(function ($company) {
        expect(CompanySetting::getSetting('credit_note_number_format', $company->id))
            ->toBe('{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:6}}');
    });
});
