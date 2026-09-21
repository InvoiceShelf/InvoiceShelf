<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Application\CustomFieldModelCatalog;
use App\Domains\Metadata\Models\CustomField;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Registry;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * The model a custom field attaches to used to be a hardcoded array in a Vue
 * component, with the column accepting any string at all. The catalogue is
 * now the one source: the editor's dropdown, the config endpoint and the
 * validation rule all read it.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

afterEach(function () {
    Registry::flushDrivers();
});

test('the catalogue offers the built-in models', function () {
    expect(app(CustomFieldModelCatalog::class)->keys())
        ->toBe(['Customer', 'Invoice', 'Estimate', 'Payment', 'Expense']);
});

test('the config endpoint serves the catalogue to the editor', function () {
    $response = getJson('api/v1/config?key=custom_field_models')->assertOk();

    expect($response->json('custom_field_models.0'))
        ->toBe(['value' => 'Customer', 'label' => 'settings.custom_fields.model_type.customer']);
});

test('a model the catalogue does not offer is refused', function () {
    $data = CustomField::factory()->raw(['model_type' => 'NotAThing']);

    postJson('api/v1/custom-fields', $data)->assertJsonValidationErrors('model_type');
});

test('a module can contribute a model of its own', function () {
    Registry::registerDriver(CustomFieldModelCatalog::REGISTRY_TYPE, 'Project', [
        'label' => 'Project',
        'class' => CustomField::class,
    ]);

    expect(app(CustomFieldModelCatalog::class)->keys())->toContain('Project');

    // And what the catalogue offers is what validation accepts, which is the
    // whole point of it being one list rather than two.
    postJson('api/v1/custom-fields', CustomField::factory()->raw(['model_type' => 'Project']))
        ->assertStatus(201);
});
