<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Taxation\Http\Controllers\TaxTypesController;
use App\Domains\Taxation\Http\Requests\TaxTypeRequest;
use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get tax types', function () {
    $response = getJson('api/v1/tax-types');

    $response->assertOk();
});

test('create tax type', function () {
    $taxType = TaxType::factory()->raw();

    postJson('api/v1/tax-types', $taxType);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        TaxTypesController::class,
        'store',
        TaxTypeRequest::class
    );
});

test('get tax type', function () {
    $taxType = TaxType::factory()->create();

    $response = getJson('api/v1/tax-types/'.$taxType->id);

    $response->assertOk();
});

test('update tax type', function () {
    $taxType = TaxType::factory()->create();

    $taxType1 = TaxType::factory()->raw();

    $response = putJson('api/v1/tax-types/'.$taxType->id, $taxType1);

    $response->assertOk();
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        TaxTypesController::class,
        'update',
        TaxTypeRequest::class
    );
});

test('delete tax type', function () {
    $taxType = TaxType::factory()->create();

    $response = deleteJson('api/v1/tax-types/'.$taxType->id);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertModelMissing($taxType);
});

test('create negative tax type', function () {
    $taxType = TaxType::factory()->raw([
        'percent' => -9.99,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('create fixed amount tax type', function () {
    $taxType = TaxType::factory()->raw([
        'calculation_type' => 'fixed',
        'percent' => null,
        'fixed_amount' => 5000,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('defaults tax type transaction type to sales for legacy create requests', function () {
    $taxType = TaxType::factory()->raw();
    unset($taxType['transaction_type']);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_SALES);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);
});

test('creates purchase tax types and returns their transaction type', function () {
    $taxType = TaxType::factory()->raw([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('preserves transaction type when legacy updates omit it', function () {
    $taxType = TaxType::factory()->create([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
    $payload = TaxType::factory()->raw();
    unset($payload['transaction_type']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);
});

test('filters tax types by transaction type', function () {
    $companyId = User::find(1)->companies()->first()->id;
    TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);
    $purchaseTaxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    getJson('api/v1/tax-types?limit=all&transaction_type=purchases')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $purchaseTaxType->id)
        ->assertJsonPath('data.0.transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES);
});

test('rejects unknown transaction types', function () {
    $taxType = TaxType::factory()->raw(['transaction_type' => 'other']);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transaction_type');
});

test('creates a compound tax type', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => true,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201)
        ->assertJsonPath('data.compound_tax', true);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'compound_tax' => 1,
    ]);
});

test('creates a non-compound tax type when compound_tax is explicitly false', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => false,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201)
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'compound_tax' => 0,
    ]);
});

test('creates a non-compound tax type when compound_tax is omitted', function () {
    $taxType = TaxType::factory()->raw();
    unset($taxType['compound_tax']);

    postJson('api/v1/tax-types', $taxType)
        ->assertStatus(201)
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'compound_tax' => 0,
    ]);
});

test('updates a tax type to explicitly disable compound tax', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
    ]);

    $payload = TaxType::factory()->raw([
        'compound_tax' => false,
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'compound_tax' => 0,
    ]);
});

test('preserves compound tax when updates omit the key', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
    ]);

    $payload = TaxType::factory()->raw();
    // TaxType::factory()->raw() defaults compound_tax to 0 — unset it so the
    // request omits the key entirely, instead of silently sending false.
    unset($payload['compound_tax']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.compound_tax', true);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'compound_tax' => 1,
    ]);
});

test('rejects non-boolean compound_tax values', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => 'not-a-bool',
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

test('rejects null compound_tax values', function () {
    $taxType = TaxType::factory()->raw([
        'compound_tax' => null,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

test('rejects null compound_tax values on update', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
    ]);

    $payload = TaxType::factory()->raw([
        'compound_tax' => null,
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'compound_tax' => 1,
    ]);
});

test('rejects compound fixed tax types', function () {
    $taxType = TaxType::factory()->raw([
        'calculation_type' => 'fixed',
        'percent' => null,
        'fixed_amount' => 500,
        'compound_tax' => true,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

test('rejects compound purchase tax types', function () {
    $taxType = TaxType::factory()->raw([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
        'compound_tax' => true,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

test('rejects a type change that leaves compound tax enabled', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
        'calculation_type' => 'percentage',
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);

    $payload = TaxType::factory()->raw([
        'calculation_type' => 'fixed',
        'percent' => null,
        'fixed_amount' => 500,
    ]);
    unset($payload['compound_tax']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('compound_tax');
});

test('defaults new tax types to the standard tax category code', function () {
    $taxType = TaxType::factory()->raw();
    unset($taxType['tax_category_code']);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.tax_category_code', TaxType::TAX_CATEGORY_CODE_STANDARD)
        ->assertJsonPath('data.tax_exemption_reason', null);

    $this->assertDatabaseHas('tax_types', [
        'name' => $taxType['name'],
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_STANDARD,
        'tax_exemption_reason' => null,
    ]);
});

test('defaults tax types created before the migration to the standard tax category code', function () {
    TaxType::query()->insert([
        'name' => 'Legacy Tax',
        'percent' => 19,
        'calculation_type' => 'percentage',
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
        'type' => TaxType::TYPE_GENERAL,
        'compound_tax' => 0,
        'collective_tax' => 0,
        'company_id' => User::find(1)->companies()->first()->id,
    ]);

    expect(TaxType::where('name', 'Legacy Tax')->firstOrFail()->tax_category_code)
        ->toBe(TaxType::TAX_CATEGORY_CODE_STANDARD);
});

test('creates a tax type with an exempt category code and an exemption reason', function () {
    $taxType = TaxType::factory()->raw([
        'percent' => 0,
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_EXEMPT,
        'tax_exemption_reason' => '§ 19 UStG',
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertCreated()
        ->assertJsonPath('data.tax_category_code', TaxType::TAX_CATEGORY_CODE_EXEMPT)
        ->assertJsonPath('data.tax_exemption_reason', '§ 19 UStG');

    $this->assertDatabaseHas('tax_types', $taxType);
});

test('rejects unknown tax category codes', function () {
    $taxType = TaxType::factory()->raw([
        'tax_category_code' => 'XX',
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_category_code');
});

test('requires an exemption reason for exempt tax category codes', function () {
    $taxType = TaxType::factory()->raw([
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_EXEMPT,
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_exemption_reason');
});

test('rejects an exemption reason longer than the stored maximum', function () {
    $taxType = TaxType::factory()->raw([
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_EXEMPT,
        'tax_exemption_reason' => str_repeat('a', 256),
    ]);

    postJson('api/v1/tax-types', $taxType)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_exemption_reason');
});

test('updates a tax type to a reverse charge category code', function () {
    $taxType = TaxType::factory()->create();

    $payload = TaxType::factory()->raw([
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_REVERSE_CHARGE,
        'tax_exemption_reason' => 'Reverse charge',
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.tax_category_code', TaxType::TAX_CATEGORY_CODE_REVERSE_CHARGE)
        ->assertJsonPath('data.tax_exemption_reason', 'Reverse charge');

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_REVERSE_CHARGE,
        'tax_exemption_reason' => 'Reverse charge',
    ]);
});

test('preserves the tax category code when updates omit it', function () {
    $taxType = TaxType::factory()->exempt('§ 19 UStG')->create();

    $payload = TaxType::factory()->raw();
    unset($payload['tax_category_code'], $payload['tax_exemption_reason']);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.tax_category_code', TaxType::TAX_CATEGORY_CODE_EXEMPT)
        ->assertJsonPath('data.tax_exemption_reason', '§ 19 UStG');

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_EXEMPT,
        'tax_exemption_reason' => '§ 19 UStG',
    ]);
});

test('clears the exemption reason when the tax category code no longer needs one', function () {
    $taxType = TaxType::factory()->exempt('§ 19 UStG')->create();

    $payload = TaxType::factory()->raw([
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_STANDARD,
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.tax_category_code', TaxType::TAX_CATEGORY_CODE_STANDARD)
        ->assertJsonPath('data.tax_exemption_reason', null);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'tax_category_code' => TaxType::TAX_CATEGORY_CODE_STANDARD,
        'tax_exemption_reason' => null,
    ]);
});

test('allows clearing compound tax while changing its type', function () {
    $taxType = TaxType::factory()->create([
        'compound_tax' => true,
        'calculation_type' => 'percentage',
        'transaction_type' => TaxType::TRANSACTION_TYPE_SALES,
    ]);

    $payload = TaxType::factory()->raw([
        'calculation_type' => 'fixed',
        'percent' => null,
        'fixed_amount' => 500,
        'compound_tax' => false,
    ]);

    putJson("api/v1/tax-types/{$taxType->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.compound_tax', false);

    $this->assertDatabaseHas('tax_types', [
        'id' => $taxType->id,
        'calculation_type' => 'fixed',
        'compound_tax' => 0,
    ]);
});
