<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Answers used to arrive with no rule of their own, so a bad id was dropped
 * by the writer without a word and the whole shape was invisible to the
 * generated API spec, which Scramble builds from rules().
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
    ]);
});

test('an answer naming another company definition is refused rather than dropped', function () {
    $foreign = CustomField::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'model_type' => 'Item',
        'type' => 'Input',
    ]);

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['id' => $foreign->id, 'value' => 'not yours']],
    ]))->assertJsonValidationErrors('customFields.0.id');
});

test('a malformed entry is refused', function (array $entry, string $error) {
    postJson('api/v1/items', Item::factory()->raw(['customFields' => [$entry]]))
        ->assertJsonValidationErrors($error);
})->with([
    'no id' => [['value' => 'orphan'], 'customFields.0.id'],
    'an id that is not a number' => [['id' => 'abc', 'value' => 'x'], 'customFields.0.id'],
    'an id that names nothing' => [['id' => 999999, 'value' => 'x'], 'customFields.0.id'],
    'no value at all' => [['id' => 1], 'customFields.0.value'],
]);

test('answering the same definition twice on one record is refused', function () {
    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [
            ['id' => $this->field->id, 'value' => 'first'],
            ['id' => $this->field->id, 'value' => 'second'],
        ],
    ]))->assertJsonValidationErrors('customFields.0.id');
});

test('the payload the admin interface actually sends is accepted', function () {
    // The form posts each answer as the whole definition with a value added,
    // not a tidy {id, value} pair. A rule that whitelisted keys would reject
    // the application's own requests.
    $asTheFormSendsIt = [
        'id' => $this->field->id,
        'value' => 'from the form',
        'label' => $this->field->label,
        'type' => 'Input',
        'options' => null,
        'is_required' => false,
        'placeholder' => null,
        'order' => 1,
        'default_answer' => null,
        'custom_field_id' => $this->field->id,
    ];

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [$asTheFormSendsIt],
    ]))->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $this->field->id,
        'string_answer' => 'from the form',
    ]);
});

test('the key never reaches the record it was posted with', function () {
    postJson('api/v1/items', Item::factory()->raw([
        'name' => 'Guarded',
        'customFields' => [['id' => $this->field->id, 'value' => 'x']],
    ]))->assertSuccessful();

    // Declaring the key in rules() puts it in validated(), which several
    // endpoints hand straight to a model. Eloquent guards any key that is not
    // a real column, and this is the test that says so out loud.
    $item = Item::query()->where('name', 'Guarded')->sole();

    expect($item->getAttributes())->not->toHaveKey('customFields');
});
