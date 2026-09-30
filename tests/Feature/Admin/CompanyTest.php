<?php

use App\Domains\Accounts\Http\Controllers\Admin\CompaniesController;
use App\Domains\Accounts\Http\Requests\CompaniesRequest;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

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

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        CompaniesController::class,
        'store',
        CompaniesRequest::class
    );
});

test('store company', function () {
    $company = Company::factory()->raw([
        'currency' => 12,
        'address' => [
            'country_id' => 12,
        ],
    ]);

    postJson('/api/v1/companies', $company)
        ->assertStatus(201);

    $company = collect($company)
        ->only([
            'name',
        ])
        ->toArray();

    $this->assertDatabaseHas('companies', $company);
});

test('a super administrator can create a company without an active company header', function () {
    $this->withoutHeader('company');

    $company = Company::factory()->raw([
        'currency' => 12,
        'address' => [
            'country_id' => 12,
        ],
    ]);

    postJson('/api/v1/companies', $company)
        ->assertCreated();

    $this->assertDatabaseHas('companies', [
        'name' => $company['name'],
        'owner_id' => User::find(1)->id,
    ]);
});

test('delete company', function () {
    postJson('/api/v1/companies/delete', ['xyz'])
        ->assertStatus(422);
});

test('transfer ownership', function () {
    $company = Company::factory()->create();

    $user = User::factory()->create();

    postJson('/api/v1/transfer/ownership/'.$user->id)
        ->assertOk();
});

test('get companies', function () {
    getJson('/api/v1/companies')
        ->assertOk();
});
