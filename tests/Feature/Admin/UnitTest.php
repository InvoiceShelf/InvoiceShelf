<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Http\Controllers\UnitsController;
use App\Domains\Catalog\Http\Requests\UnitRequest;
use App\Domains\Catalog\Models\Unit;
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

test('get units', function () {
    $response = getJson('api/v1/units?page=1');

    $response->assertOk();
});

test('create unit', function () {
    $data = [
        'name' => 'unit name',
        'company_id' => User::find(1)->companies()->first()->id,
    ];

    $response = postJson('api/v1/units', $data);

    $response->assertStatus(201);

    $this->assertDatabaseHas('units', $data);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        UnitsController::class,
        'store',
        UnitRequest::class
    );
});

test('get unit', function () {
    $unit = Unit::factory()->create();

    $response = getJson("api/v1/units/{$unit->id}");

    $response->assertOk();

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'name' => $unit['name'],
    ]);
});

test('update unit', function () {
    $unit = Unit::factory()->create();

    $update_unit = [
        'name' => 'new name',
    ];

    $response = putJson("api/v1/units/{$unit->id}", $update_unit);

    $response->assertOk();

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'name' => $update_unit['name'],
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        UnitsController::class,
        'update',
        UnitRequest::class
    );
});

test('delete unit', function () {
    $unit = Unit::factory()->create();

    $response = deleteJson("api/v1/units/{$unit->id}");

    $response->assertOk();

    $this->assertModelMissing($unit);
});

test('creates a unit with the default unit code when none is given', function () {
    $data = [
        'name' => 'unit without a code',
    ];

    postJson('api/v1/units', $data)
        ->assertCreated()
        ->assertJsonPath('data.unit_code', 'C62');

    $this->assertDatabaseHas('units', [
        'name' => $data['name'],
        'unit_code' => 'C62',
    ]);
});

test('creates a unit with an explicit unit code', function () {
    $data = [
        'name' => 'hours',
        'unit_code' => 'HUR',
    ];

    postJson('api/v1/units', $data)
        ->assertCreated()
        ->assertJsonPath('data.unit_code', 'HUR');

    $this->assertDatabaseHas('units', $data);
});

test('rejects unknown unit codes', function () {
    postJson('api/v1/units', [
        'name' => 'bogus unit',
        'unit_code' => 'NOPE',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_code');

    $this->assertDatabaseMissing('units', ['name' => 'bogus unit']);
});

test('rejects a null unit code', function () {
    postJson('api/v1/units', [
        'name' => 'null coded unit',
        'unit_code' => null,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_code');

    $this->assertDatabaseMissing('units', ['name' => 'null coded unit']);
});

test('exposes the unit code when reading a unit', function () {
    $unit = Unit::factory()->create(['unit_code' => 'KGM']);

    getJson("api/v1/units/{$unit->id}")
        ->assertOk()
        ->assertJsonPath('data.unit_code', 'KGM');
});

test('updates the unit code', function () {
    $unit = Unit::factory()->create(['unit_code' => 'C62']);

    putJson("api/v1/units/{$unit->id}", [
        'name' => 'days',
        'unit_code' => 'DAY',
    ])
        ->assertOk()
        ->assertJsonPath('data.unit_code', 'DAY');

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'unit_code' => 'DAY',
    ]);
});

test('preserves the unit code when updates omit it', function () {
    $unit = Unit::factory()->create(['unit_code' => 'HUR']);

    putJson("api/v1/units/{$unit->id}", ['name' => 'renamed unit'])
        ->assertOk()
        ->assertJsonPath('data.unit_code', 'HUR');

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'name' => 'renamed unit',
        'unit_code' => 'HUR',
    ]);
});

test('rejects unknown unit codes on update', function () {
    $unit = Unit::factory()->create(['unit_code' => 'HUR']);

    putJson("api/v1/units/{$unit->id}", [
        'name' => 'still hours',
        'unit_code' => 'NOPE',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_code');

    $this->assertDatabaseHas('units', [
        'id' => $unit->id,
        'unit_code' => 'HUR',
    ]);
});

test('gives every pre-existing unit the default unit code', function () {
    $companyId = User::find(1)->companies()->first()->id;
    $units = Unit::where('company_id', $companyId)->get();

    expect($units)->not->toBeEmpty()
        ->and($units->pluck('unit_code')->unique()->values()->all())->toBe(['C62']);
});

test('offers a curated list of unit codes through the config endpoint', function () {
    $response = getJson('api/v1/config?key=unit_codes')->assertOk();

    $codes = collect($response->json('unit_codes'))->pluck('value')->all();

    expect($codes)->toContain('C62', 'HUR', 'DAY', 'KGM', 'LTR', 'MTR');
});
