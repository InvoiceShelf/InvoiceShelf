<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * The three model types added last: a catalogue item, the company itself and
 * a user. Item is the one people asked for most (#315), Company next (#643).
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);
});

function definitionFor(string $model): CustomField
{
    return CustomField::factory()->create([
        'company_id' => test()->company->id,
        'model_type' => $model,
        'type' => 'Input',
    ]);
}

test('a catalogue item keeps the answers it was sent', function () {
    $field = definitionFor('Item');

    $payload = Item::factory()->raw([
        'customFields' => [['id' => $field->id, 'value' => 'SKU-9000']],
    ]);

    postJson('api/v1/items', $payload)->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'SKU-9000',
    ]);
});

test('editing a catalogue item replaces its answer rather than adding one', function () {
    $field = definitionFor('Item');
    $item = Item::factory()->create(['company_id' => $this->company->id]);

    app(CustomFieldValueWriter::class)
        ->attach($item, [['id' => $field->id, 'value' => 'first']]);

    putJson("api/v1/items/{$item->id}", Item::factory()->raw([
        'name' => $item->name,
        'customFields' => [['id' => $field->id, 'value' => 'second']],
    ]))->assertSuccessful();

    expect($item->fields()->count())->toBe(1)
        ->and($item->fields()->sole()->string_answer)->toBe('second');
});

test('a catalogue item serialises its answers', function () {
    $field = definitionFor('Item');
    $item = Item::factory()->create(['company_id' => $this->company->id]);

    app(CustomFieldValueWriter::class)
        ->attach($item, [['id' => $field->id, 'value' => 'shown to the form']]);

    // The document line copies these across when the item is put on an
    // invoice, so the form has to be able to read them back.
    $this->getJson("api/v1/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.fields.0.string_answer', 'shown to the form');
});

test('the company keeps the answers it was sent and reads them back', function () {
    $field = definitionFor('Company');

    putJson('api/v1/company', [
        'name' => $this->company->name,
        'country_id' => 2,
        'address' => ['country_id' => 2],
        'customFields' => [['id' => $field->id, 'value' => 'DE89 3704 0044 0532 0130 00']],
    ])->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'DE89 3704 0044 0532 0130 00',
    ]);

    // #643 wants these reachable from a PDF template through the company.
    expect($this->company->fresh()->getCustomFieldValueBySlug($field->slug))
        ->toBe('DE89 3704 0044 0532 0130 00');
});

test('a document line keeps its own answers, separate from the item it came from', function () {
    $field = definitionFor('Item');
    $item = Item::factory()->create(['company_id' => $this->company->id]);

    app(CustomFieldValueWriter::class)
        ->attach($item, [['id' => $field->id, 'value' => '24 months']]);

    // The form copies the item's answer onto the line, then the user may
    // change it for this document only.
    $payload = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw([
            'item_id' => $item->id,
            'custom_fields' => [['id' => $field->id, 'value' => '36 months, agreed on the call']],
        ])],
    ]);

    postJson('api/v1/invoices', $payload)->assertOk();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_valuable_type' => 'invoice_item',
        'string_answer' => '36 months, agreed on the call',
    ]);

    // The catalogue entry keeps what it had.
    expect($item->fields()->sole()->string_answer)->toBe('24 months');
});

test('a company cannot answer another company definition', function () {
    $foreign = CustomField::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'model_type' => 'Company',
        'type' => 'Input',
    ]);

    // A company carries no company_id of its own, so the writer has to ask it
    // which company it is rather than read an attribute that is not there.
    app(CustomFieldValueWriter::class)
        ->update($this->company, [['id' => $foreign->id, 'value' => 'leaked']]);

    expect($this->company->fields()->count())->toBe(0);
});
