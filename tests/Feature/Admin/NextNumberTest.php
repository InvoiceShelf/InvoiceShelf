<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'CurrenciesTableSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'CountriesTableSeeder', '--force' => true]);

    $user = User::factory()->create();
    $company = Company::factory()->create([
        'owner_id' => $user->id,
    ]);
    $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
    $company->save();

    $company->setupDefaultData();
    $user->companies()->attach($company->id);
    BouncerFacade::scope()->to($company->id);
    BouncerFacade::sync($user)->roles(['super admin']);

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('next number', function () {
    $key = 'invoice';

    $response = getJson('api/v1/next-number?key='.$key);

    $response->assertStatus(200)->assertJson([
        'nextNumber' => 'INV-000001',
    ]);

    $key = 'estimate';

    $response = getJson('api/v1/next-number?key='.$key);

    $response->assertStatus(200)->assertJson([
        'nextNumber' => 'EST-000001',
    ]);

    $key = 'payment';

    $response = getJson('api/v1/next-number?key='.$key);

    $response->assertStatus(200)->assertJson([
        'nextNumber' => 'PAY-000001',
    ]);
});
