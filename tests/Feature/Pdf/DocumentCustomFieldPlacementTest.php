<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * Until now a custom field only reached a PDF if someone edited a Blade
 * template, which is what #237 and a run of "my custom field is not on the
 * invoice" reports were really about. A definition now says for itself.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    Sanctum::actingAs($user, ['*']);

    $this->invoice = Invoice::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => 'invoice1',
    ]);
});

function answeredField(string $placement, string $label, string $answer): CustomField
{
    $field = CustomField::factory()->create([
        'company_id' => test()->company->id,
        'model_type' => 'Invoice',
        'type' => 'Input',
        'label' => $label,
        'placement' => $placement,
    ]);

    app(CustomFieldValueWriter::class)
        ->attach(test()->invoice, [['id' => $field->id, 'value' => $answer]]);

    return $field;
}

test('a field marked for the document is printed with the dates', function () {
    answeredField('document', 'Delivery Date', '2026-09-30');

    $html = get("/invoices/pdf/{$this->invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->toContain('Delivery Date')->toContain('2026-09-30');
});

test('an internal field never reaches the page', function () {
    answeredField('internal', 'Internal Reference', 'ACC-1234');

    $html = get("/invoices/pdf/{$this->invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->not->toContain('Internal Reference')
        ->and($html)->not->toContain('ACC-1234');
});

test('a printed field with no answer leaves no empty row behind', function () {
    CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Invoice',
        'type' => 'Input',
        'label' => 'Purchase Order',
        'placement' => 'document',
    ]);

    $html = get("/invoices/pdf/{$this->invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->not->toContain('Purchase Order');
});

test('a new definition is internal until someone says otherwise', function () {
    $field = CustomField::factory()->create(['model_type' => 'Invoice']);

    expect($field->fresh()->placement)->toBe('internal');
});

test('line-level columns follow the same flag', function () {
    $printed = CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'label' => 'Warranty',
        'placement' => 'document',
    ]);

    CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'label' => 'Internal Note',
        'placement' => 'internal',
    ]);

    app(CustomFieldValueWriter::class)
        ->attach($this->invoice->items->first(), [['id' => $printed->id, 'value' => '24 months']]);

    $html = get("/invoices/pdf/{$this->invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->toContain('Warranty')->toContain('24 months')
        ->and($html)->not->toContain('Internal Note');
});

test('a printed date matches the format the other dates use', function () {
    $field = CustomField::factory()->create([
        'company_id' => $this->company->id,
        'model_type' => 'Invoice',
        'type' => 'Date',
        'label' => 'Supply Date',
        'placement' => 'document',
    ]);

    app(CustomFieldValueWriter::class)
        ->attach($this->invoice, [['id' => $field->id, 'value' => '2026-09-30']]);

    // #237 is a date request: printing the stored 2026-09-30 beside an
    // invoice date rendered as 2026/09/30 is not an answer to it.
    $expected = Carbon::parse('2026-09-30')->format(
        CompanySetting::getSetting('carbon_date_format', $this->company->id)
    );

    $html = get("/invoices/pdf/{$this->invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->toContain('Supply Date')->toContain($expected);
});
