<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * A definition can now describe what a valid answer looks like, and the
 * server enforces it. It also finally enforces is_required, which lived on
 * the definition and was honoured only by the browser.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function itemFieldWith(array $attributes): CustomField
{
    return CustomField::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'is_required' => false,
    ], $attributes));
}

function postItemAnswering(CustomField $field, mixed $value): TestResponse
{
    return postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['id' => $field->id, 'value' => $value]],
    ]));
}

test('a required answer left empty is refused', function (mixed $blank) {
    $field = itemFieldWith(['is_required' => true, 'label' => 'Warranty']);

    postItemAnswering($field, $blank)
        ->assertJsonValidationErrors(['customFields.0.value' => 'Warranty is required.']);
})->with([
    'null' => [null],
    'an empty string' => [''],
]);

test('a switch turned off is an answer, not a missing one', function () {
    $field = itemFieldWith(['is_required' => true, 'type' => 'Switch']);

    postItemAnswering($field, 0)->assertSuccessful();
});

test('a value outside the configured length is refused', function (string $value, string $expected) {
    $field = itemFieldWith([
        'label' => 'Code',
        'validation' => ['min_length' => 3, 'max_length' => 5],
    ]);

    postItemAnswering($field, $value)
        ->assertJsonValidationErrors(['customFields.0.value' => $expected]);
})->with([
    'too short' => ['ab', 'Code must be at least 3 characters.'],
    'too long' => ['abcdefg', 'Code may not be longer than 5 characters.'],
]);

test('a number outside the configured range is refused', function (int $value, string $expected) {
    $field = itemFieldWith([
        'label' => 'Warranty months',
        'type' => 'Number',
        'validation' => ['min' => 6, 'max' => 36],
    ]);

    postItemAnswering($field, $value)
        ->assertJsonValidationErrors(['customFields.0.value' => $expected]);
})->with([
    'below' => [3, 'Warranty months may not be less than 6.'],
    'above' => [48, 'Warranty months may not be greater than 36.'],
]);

test('a value failing the pattern is refused and one matching it is kept', function () {
    // The EN 16931 VAT category codes the e-invoicing spec names are exactly
    // the sort of code list this exists for.
    $field = itemFieldWith(['label' => 'VAT category', 'validation' => ['pattern' => '^[SZEAKGO]$']]);

    postItemAnswering($field, 'Q')
        ->assertJsonValidationErrors(['customFields.0.value' => 'VAT category is not in the expected format.']);

    postItemAnswering($field, 'S')->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'S',
    ]);
});

test('a definition carrying no validation accepts what it always accepted', function () {
    $field = itemFieldWith(['validation' => null]);

    postItemAnswering($field, 'anything at all, 40 characters or so')->assertSuccessful();
});

test('a pattern PCRE will not compile is refused when the field is defined', function () {
    postJson('api/v1/custom-fields', CustomField::factory()->raw([
        'validation' => ['pattern' => '^[unclosed'],
    ]))->assertJsonValidationErrors('validation.pattern');
});

test('a bound below its own minimum is refused when the field is defined', function (array $validation, string $key) {
    postJson('api/v1/custom-fields', CustomField::factory()->raw(['validation' => $validation]))
        ->assertJsonValidationErrors($key);
})->with([
    'max_length under min_length' => [['min_length' => 10, 'max_length' => 2], 'validation.max_length'],
    'max under min' => [['min' => 10, 'max' => 2], 'validation.max'],
]);

test('a workable validation object is stored and read back as one', function () {
    $data = CustomField::factory()->raw(['validation' => ['min_length' => 2, 'pattern' => '^[A-Z]+$']]);

    postJson('api/v1/custom-fields', $data)->assertStatus(201);

    expect(CustomField::query()->where('name', $data['name'])->sole()->validation)
        ->toBe(['min_length' => 2, 'pattern' => '^[A-Z]+$']);
});

test('the definition serialises its validation so the editor can load it back', function () {
    $field = itemFieldWith(['validation' => ['min_length' => 2, 'pattern' => '^[A-Z]+$']]);

    // Missed first time round: the editor saved the object happily and then
    // showed empty inputs when reopened, because nothing sent it back.
    Pest\Laravel\getJson("api/v1/custom-fields/{$field->id}")
        ->assertOk()
        ->assertJsonPath('data.validation', ['min_length' => 2, 'pattern' => '^[A-Z]+$']);
});

test('a dropdown answer must be one of the options offered', function () {
    $field = itemFieldWith([
        'label' => 'Colour',
        'type' => 'Dropdown',
        'options' => ['Red', 'Green'],
    ]);

    // The widget limits what can be picked in the browser and nothing
    // limited what the API would take, so the option list was decoration.
    postItemAnswering($field, 'Chartreuse')
        ->assertJsonValidationErrors(['customFields.0.value' => 'Colour must be one of the options offered.']);

    postItemAnswering($field, 'Green')->assertSuccessful();
});

test('a dropdown with no options set constrains nothing', function () {
    $field = itemFieldWith(['type' => 'Dropdown', 'options' => []]);

    postItemAnswering($field, 'anything')->assertSuccessful();
});
