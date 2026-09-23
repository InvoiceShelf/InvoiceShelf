<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Http\Controllers\Company\CustomersController;
use App\Domains\Contacts\Http\Requests\CustomerRequest;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

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

test('get customers', function () {
    $response = getJson('api/v1/customers?page=1');

    $response->assertOk();
});

test('get all customers hydrates account summaries without pagination', function () {
    $customer = Customer::factory()->create([
        'company_id' => User::find(1)->companies()->first()->id,
    ]);

    getJson('api/v1/customers?limit=all')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $customer->id,
            'invoice_due_amount' => 0,
            'available_credit' => 0,
            'account_balance' => 0,
        ]);
});

test('customer stats', function () {
    $customer = Customer::factory()->create();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $response = getJson("api/v1/customers/{$customer->id}/stats");

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.name', $customer->name)
        ->assertJsonPath('data.email', $customer->email)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
            ],
            'meta' => [
                'chartData' => [
                    'salesTotal',
                    'totalReceipts',
                    'totalExpenses',
                    'netProfit',
                    'expenseTotals',
                    'netProfits',
                    'months',
                    'receiptTotals',
                    'invoiceTotals',
                ],
            ],
        ]);
});

test('customer stats cover a custom range, for that customer alone', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();

    Invoice::factory()->create(['customer_id' => $customer->id, 'invoice_date' => '2026-02-10', 'base_total' => 1200]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'invoice_date' => '2026-05-01', 'base_total' => 999]);
    Invoice::factory()->create(['customer_id' => $other->id, 'invoice_date' => '2026-02-10', 'base_total' => 5000]);

    $response = getJson("api/v1/customers/{$customer->id}/stats?from_date=2026-01-01&to_date=2026-03-31")
        ->assertOk();

    expect($response->json('meta.chartData.months'))->toBe(['Jan', 'Feb', 'Mar'])
        ->and($response->json('meta.chartData.invoiceTotals'))->toBe([0, 1200, 0])
        ->and($response->json('meta.chartData.salesTotal'))->toBe(1200)
        ->and($response->json('meta.chartData.period'))
        ->toBe(['from' => '2026-01-01', 'to' => '2026-03-31', 'granularity' => 'month']);
});

test('customer stats refuse a reversed range', function () {
    $customer = Customer::factory()->create();

    getJson("api/v1/customers/{$customer->id}/stats?from_date=2026-03-01&to_date=2026-01-01")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_date');
});

test('create customer', function () {
    $customer = Customer::factory()->raw([
        'shipping' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
        'billing' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
    ]);

    postJson('api/v1/customers', $customer)
        ->assertOk();

    $this->assertDatabaseHas('customers', [
        'name' => $customer['name'],
        'email' => $customer['email'],
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        CustomersController::class,
        'store',
        CustomerRequest::class
    );
});

test('get customer', function () {
    $customer = Customer::factory()->create();

    $response = getJson("api/v1/customers/{$customer->id}");

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => $customer['name'],
        'email' => $customer['email'],
    ]);

    $response->assertOk();
});

test('update customer', function () {
    $customer = Customer::factory()->create();

    $customer1 = Customer::factory()->raw([
        'shipping' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
        'billing' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
    ]);

    $response = putJson('api/v1/customers/'.$customer->id, $customer1);

    $customer1 = collect($customer1)
        ->only([
            'email',
        ])
        ->merge([
            'creator_id' => Auth::id(),
        ])
        ->toArray();

    $response->assertOk();

    $this->assertDatabaseHas('customers', $customer1);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        CustomersController::class,
        'update',
        CustomerRequest::class
    );
});

test('search customers', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'email' => '.com',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/customers?'.$queryString);

    $response->assertOk();
});

test('delete multiple customer', function () {
    $customers = Customer::factory()->count(4)->create();

    $ids = $customers->pluck('id');

    $data = [
        'ids' => $ids,
    ];

    $response = postJson('api/v1/customers/delete', $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});

test('cannot view customer from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherCustomer = Customer::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    getJson("api/v1/customers/{$otherCustomer->id}")
        ->assertForbidden();
});

test('cannot update customer from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherCustomer = Customer::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    putJson("api/v1/customers/{$otherCustomer->id}", [
        'name' => 'Hacked Name',
        'email' => 'hacked@example.com',
    ])->assertForbidden();
});

test('cannot bulk delete customer from another company', function () {
    $otherCompany = Company::factory()->create();
    $otherCustomer = Customer::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    postJson('api/v1/customers/delete', [
        'ids' => [$otherCustomer->id],
    ])->assertOk();

    $this->assertDatabaseHas('customers', [
        'id' => $otherCustomer->id,
    ]);
});
