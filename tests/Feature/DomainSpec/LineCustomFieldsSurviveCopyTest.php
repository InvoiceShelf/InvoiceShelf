<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

/**
 * Duplicating or converting a document copies the document's own answers but
 * used to hand its lines on as a plain array, which carries no answers at
 * all. Harmless while no line-level definition could be created; a silent
 * loss now that they can.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);

    $this->field = CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'label' => 'Warranty',
        'placement' => 'document',
    ]);
});

function answerFirstLine(mixed $document, CustomField $field, string $value): void
{
    app(CustomFieldValueWriter::class)
        ->attach($document->items->first(), [['id' => $field->id, 'value' => $value]]);
}

test('duplicating an invoice keeps each line answer', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['company_id' => $this->company->id]);
    answerFirstLine($invoice, $this->field, '24 months');

    $copy = app(InvoiceService::class)->clone($invoice->fresh());

    expect($copy->items->first()->fields()->sole()->string_answer)->toBe('24 months');
});

test('converting an estimate to an invoice keeps each line answer', function () {
    $estimate = Estimate::factory()->hasItems(1)->create(['company_id' => $this->company->id]);
    answerFirstLine($estimate, $this->field, 'on site, 12 months');

    $invoice = app(EstimateService::class)->convertToInvoice($estimate->fresh());

    expect($invoice->items->first()->fields()->sole()->string_answer)
        ->toBe('on site, 12 months');
});

test('duplicating an estimate keeps each line answer', function () {
    $estimate = Estimate::factory()->hasItems(1)->create(['company_id' => $this->company->id]);
    answerFirstLine($estimate, $this->field, 'parts only');

    $copy = app(EstimateService::class)->clone($estimate->fresh());

    expect($copy->items->first()->fields()->sole()->string_answer)->toBe('parts only');
});

test('converting an invoice to an estimate keeps each line answer', function () {
    $invoice = Invoice::factory()->hasItems(1)->create(['company_id' => $this->company->id]);
    answerFirstLine($invoice, $this->field, 'extended cover');

    $estimate = app(InvoiceService::class)->convertToEstimate($invoice->fresh());

    expect($estimate->items->first()->fields()->sole()->string_answer)->toBe('extended cover');
});
