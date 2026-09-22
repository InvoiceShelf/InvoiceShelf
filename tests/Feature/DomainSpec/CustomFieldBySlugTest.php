<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Metadata\Application\CustomFieldService;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * An answer used to name its definition by numeric id alone, so an
 * integrator had to fetch the definitions first and the id differed per
 * install. The slug is minted once, survives a rename and is what the
 * templates already use.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);
});

function slugField(string $model = 'Item'): CustomField
{
    return CustomField::factory()->create([
        'company_id' => test()->company->id,
        'model_type' => $model,
        'type' => 'Input',
        'slug' => 'CUSTOM_'.strtoupper($model).'_WARRANTY',
    ]);
}

test('an answer addressed by slug is stored', function () {
    $field = slugField();

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['slug' => $field->slug, 'value' => '24 months']],
    ]))->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => '24 months',
    ]);
});

test('an answer addressed by id still works', function () {
    $field = slugField();

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['id' => $field->id, 'value' => 'by id']],
    ]))->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', ['string_answer' => 'by id']);
});

test('the admin interface posts both and that keeps working', function () {
    // The form submits the whole definition with a value added, so every
    // answer it sends carries an id and a slug. Refusing that combination
    // would refuse the application's own requests.
    $field = slugField();

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [[
            'id' => $field->id,
            'slug' => $field->slug,
            'label' => $field->label,
            'type' => 'Input',
            'value' => 'from the form',
        ]],
    ]))->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', ['string_answer' => 'from the form']);
});

test('a slug belonging to another company is refused', function () {
    $foreign = CustomField::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'model_type' => 'Item',
        'type' => 'Input',
        'slug' => 'CUSTOM_ITEM_SOMEONE_ELSES',
    ]);

    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['slug' => $foreign->slug, 'value' => 'not yours']],
    ]))->assertJsonValidationErrors('customFields.0.slug');
});

test('an entry naming neither an id nor a slug is refused', function () {
    postJson('api/v1/items', Item::factory()->raw([
        'customFields' => [['value' => 'orphan']],
    ]))->assertJsonValidationErrors('customFields.0.id');
});

test('two companies naming a field alike both get the plain slug', function () {
    // This is what was wrong: uniqueness was global, so the second tenant
    // got a _1 suffix decided by a company they cannot see, and the
    // eleventh could not create the field at all.
    $other = Company::factory()->create();
    $service = app(CustomFieldService::class);

    $attributes = [
        'name' => 'Supply Date',
        'label' => 'Supply Date',
        'model_type' => 'Invoice',
        'type' => 'Input',
        'order' => 1,
        'is_required' => false,
    ];

    $mine = $service->create($attributes, null, $this->company->id);
    $theirs = $service->create($attributes, null, $other->id);

    expect($mine->slug)->toBe('CUSTOM_INVOICE_SUPPLY_DATE')
        ->and($theirs->slug)->toBe('CUSTOM_INVOICE_SUPPLY_DATE');
});

test('a slug on a user resolves through the company the caller is acting for', function () {
    // A user belongs to several companies and so names none itself; without
    // the header the slug would be a guess.
    $field = slugField('User');

    putJson('api/v1/me', [
        'name' => $this->user->name,
        'email' => $this->user->email,
        'customFields' => [['slug' => $field->slug, 'value' => 'on the user']],
    ])->assertSuccessful();

    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'string_answer' => 'on the user',
    ]);
});
