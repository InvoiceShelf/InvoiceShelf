<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Http\Controllers\Company\EstimatesController;
use App\Domains\Sales\Http\Requests\DeleteEstimatesRequest;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Http\Requests\SendEstimatesRequest;
use App\Domains\Sales\Mail\SendEstimateMail;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\EstimateItem;
use App\Domains\Taxation\Models\Tax;
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

test('get estimates', function () {
    $response = getJson('api/v1/estimates?page=1');

    $response->assertOk();
});

test('create estimate', function () {
    $estimate = Estimate::factory()->raw([
        'estimate_number' => 'EST-000006',
        'items' => [
            EstimateItem::factory()->raw(),
        ],
        'taxes' => [
            Tax::factory()->raw(),
        ],
    ]);

    postJson('api/v1/estimates', $estimate)
        ->assertStatus(201);

    $this->assertDatabaseHas('estimates', [
        'template_name' => $estimate['template_name'],
        'estimate_number' => $estimate['estimate_number'],
        'discount_type' => $estimate['discount_type'],
        'discount_val' => $estimate['discount_val'],
        'discount' => $estimate['discount'],
        'customer_id' => $estimate['customer_id'],
        'notes' => $estimate['notes'],
    ]);
});

test('clone estimate', function () {

    $estimate = Estimate::factory()->create();

    $beforeCount = Estimate::count();

    $response = $this->post("/api/v1/estimates/{$estimate->id}/clone");

    $this->assertDatabaseCount('estimates', $beforeCount + 1);

});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        EstimatesController::class,
        'store',
        EstimatesRequest::class
    );
});

test('update estimate', function () {
    $estimate = Estimate::factory()
        ->hasItems(1)
        ->hasTaxes(1)
        ->create([
            'estimate_date' => '1988-07-18',
            'expiry_date' => '1988-08-18',
        ]);

    $estimate2 = Estimate::factory()->raw([
        'items' => [
            EstimateItem::factory()->raw([
                'estimate_id' => $estimate->id,
            ]),
        ],
        'taxes' => [
            Tax::factory()->raw([
                'tax_type_id' => $estimate->taxes[0]->tax_type_id,
            ]),
        ],
    ]);

    $response = putJson('api/v1/estimates/'.$estimate->id, $estimate2);

    $this->assertDatabaseHas('estimates', [
        'template_name' => $estimate2['template_name'],
        'estimate_number' => $estimate2['estimate_number'],
        'discount_type' => $estimate2['discount_type'],
        'discount_val' => $estimate2['discount_val'],
        'discount' => $estimate2['discount'],
        'customer_id' => $estimate2['customer_id'],
        'notes' => $estimate2['notes'],
    ]);

    $this->assertDatabaseHas('estimate_items', [
        'estimate_id' => $estimate2['items'][0]['estimate_id'],
    ]);

    $response->assertStatus(200);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        EstimatesController::class,
        'update',
        EstimatesRequest::class
    );
});

test('search estimates', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'from_date' => '2020-07-18',
        'to_date' => '2020-07-20',
        'estimate_number' => '000003',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/estimates?'.$queryString);

    $response->assertStatus(200);
});

test('send estimate using a form request', function () {
    $this->assertActionUsesFormRequest(
        EstimatesController::class,
        'send',
        SendEstimatesRequest::class
    );
});

test('send estimate to customer', function () {
    Mail::fake();

    $estimate = Estimate::factory()->create([
        'estimate_date' => '1988-07-18',
        'expiry_date' => '1988-08-18',
    ]);

    $data = [
        'subject' => 'test',
        'body' => 'test',
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
    ];

    postJson("api/v1/estimates/{$estimate->id}/send", $data)
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    Mail::assertSent(SendEstimateMail::class);
});

test('estimate mark as accepted', function () {
    $estimate = Estimate::factory()->create([
        'estimate_date' => '1988-07-18',
        'expiry_date' => '1988-08-18',
    ]);

    $data = [
        'status' => Estimate::STATUS_ACCEPTED,
    ];

    $response = postJson("api/v1/estimates/{$estimate->id}/status", $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $estimate2 = Estimate::find($estimate->id);
    $this->assertEquals($estimate2->status, Estimate::STATUS_ACCEPTED);
});

test('estimate mark as rejected', function () {
    $estimate = Estimate::factory()->create([
        'estimate_date' => '1988-07-18',
        'expiry_date' => '1988-08-18',
    ]);

    $data = [
        'status' => Estimate::STATUS_REJECTED,
    ];

    $response = postJson("api/v1/estimates/{$estimate->id}/status", $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $estimate2 = Estimate::find($estimate->id);
    $this->assertEquals($estimate2->status, Estimate::STATUS_REJECTED);
});

test('create invoice from estimate', function () {

    $estimate = Estimate::factory()
        ->create([
            'estimate_date' => now(),
            'expiry_date' => now()->addMonth(),
        ]);

    $response = postJson("api/v1/estimates/{$estimate->id}/convert-to-invoice");

    if ($response->status() !== 200) {
        $this->fail('Response status is not 200. Response body: '.json_encode($response->json()));
    }

    $response->assertStatus(200);
});

test('cannot convert an estimate belonging to another company', function () {
    $estimate = Estimate::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'estimate_date' => now(),
        'expiry_date' => now()->addMonth(),
    ]);

    postJson("api/v1/estimates/{$estimate->id}/convert-to-invoice")->assertStatus(403);
});

test('delete multiple estimates using a form request', function () {
    $this->assertActionUsesFormRequest(
        EstimatesController::class,
        'delete',
        DeleteEstimatesRequest::class
    );
});

test('delete multiple estimates', function () {
    $estimates = Estimate::factory()
        ->count(3)
        ->create([
            'estimate_date' => '1988-07-18',
            'expiry_date' => '1988-08-18',
        ]);

    $ids = $estimates->pluck('id');

    $data = [
        'ids' => $ids,
    ];

    $response = postJson('api/v1/estimates/delete', $data);

    $response
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    foreach ($estimates as $estimate) {
        $this->assertModelMissing($estimate);
    }
});

test('get estimate templates', function () {
    getJson('api/v1/estimates/templates')->assertStatus(200);
});

test('create estimate with tax per item', function () {
    $estimate = Estimate::factory()->raw([
        'estimate_number' => 'EST-000006',
        'tax_per_item' => 'YES',
        'items' => [
            EstimateItem::factory()->raw([
                'taxes' => [Tax::factory()->raw()],
            ]),
            EstimateItem::factory()->raw([
                'taxes' => [Tax::factory()->raw()],
            ]),
        ],
    ]);

    postJson('api/v1/estimates', $estimate)
        ->assertStatus(201);

    $this->assertDatabaseHas('estimates', [
        'template_name' => $estimate['template_name'],
        'estimate_number' => $estimate['estimate_number'],
        'discount_type' => $estimate['discount_type'],
        'discount_val' => $estimate['discount_val'],
        'discount' => $estimate['discount'],
        'customer_id' => $estimate['customer_id'],
        'notes' => $estimate['notes'],
    ]);

    $this->assertDatabaseHas('estimate_items', [
        'name' => $estimate['items'][0]['name'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $estimate['items'][0]['taxes'][0]['tax_type_id'],
    ]);
});

test('persists exclusive per-item simple and compound tax totals', function () {
    $companyId = User::find(1)->companies()->first()->id;
    CompanySetting::setSettings(['tax_per_item' => 'YES'], $companyId);

    $simpleTax = Tax::factory()->raw([
        'name' => 'VAT 19%',
        'percent' => 19,
        'amount' => 1900,
        'compound_tax' => false,
    ]);
    $compoundTax = Tax::factory()->raw([
        'name' => 'Cash levy 1%',
        'percent' => 1,
        'amount' => 119,
        'compound_tax' => true,
    ]);
    $placeholderTax = [
        'tax_type_id' => 0,
        'name' => '',
        'amount' => 0,
        'percent' => null,
        'calculation_type' => null,
        'fixed_amount' => 0,
        'compound_tax' => false,
    ];
    $item = EstimateItem::factory()->raw([
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'discount_val' => 0,
        'tax' => 2019,
        'taxes' => [$simpleTax, $compoundTax, $placeholderTax],
    ]);
    $estimate = Estimate::factory()->raw([
        'items' => [$item],
        'taxes' => [],
        'discount' => 0,
        'discount_val' => 0,
        'tax_included' => false,
        'sub_total' => 1,
        'tax' => 1,
        'total' => 1,
    ]);

    postJson('api/v1/estimates', $estimate)->assertCreated();

    $savedEstimate = Estimate::query()
        ->where('estimate_number', $estimate['estimate_number'])
        ->firstOrFail();
    $savedItem = $savedEstimate->items()->firstOrFail();

    expect($savedEstimate->sub_total)->toBe(10000)
        ->and($savedEstimate->tax)->toBe(2019)
        ->and($savedEstimate->total)->toBe(12019)
        ->and($savedItem->taxes()->count())->toBe(2);

    $this->assertDatabaseHas('taxes', [
        'estimate_item_id' => $savedItem->id,
        'tax_type_id' => $simpleTax['tax_type_id'],
        'amount' => 1900,
        'compound_tax' => 0,
    ]);
    $this->assertDatabaseHas('taxes', [
        'estimate_item_id' => $savedItem->id,
        'tax_type_id' => $compoundTax['tax_type_id'],
        'amount' => 119,
        'compound_tax' => 1,
    ]);
});

test('rejects a nonzero per-item placeholder tax row', function () {
    $estimate = Estimate::factory()->raw([
        'estimate_number' => 'EST-PLACEHOLDER',
        'items' => [
            EstimateItem::factory()->raw([
                'taxes' => [[
                    'amount' => 1,
                ]],
            ]),
        ],
    ]);

    postJson('api/v1/estimates', $estimate)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.taxes.0.amount');
});

test('create estimate with EUR currency', function () {
    $estimate = Estimate::factory()
        ->raw([
            'discount_type' => 'fixed',
            'discount_val' => 20,
            'sub_total' => 200,
            'total' => 189,
            'tax' => 9,
            'exchange_rate' => 86.403538,
            'base_discount_val' => 1728,
            'base_sub_total' => 17281,
            'base_total' => 16330,
            'base_tax' => 778,
            'taxes' => [Tax::factory()->raw([
                'amount' => 9,
                'percent' => 5,
                'exchange_rate' => 86.403538,
                'base_amount' => 778,
            ])],
            'items' => [EstimateItem::factory()->raw([
                'discount_type' => 'fixed',
                'quantity' => 1,
                'discount' => 0,
                'discount_val' => 0,
                'price' => 200,
                'tax' => 0,
                'total' => 200,
                'exchange_rate' => 86.403538,
                'base_discount_val' => 0,
                'base_price' => 17281,
                'base_tax' => 0,
                'base_total' => 17281,
            ])],
        ]);

    $response = postJson('api/v1/estimates', $estimate)->assertStatus(201);

    $this->assertDatabaseHas('estimates', [
        'template_name' => $estimate['template_name'],
        'estimate_number' => $estimate['estimate_number'],
        'discount_type' => $estimate['discount_type'],
        'discount_val' => $estimate['discount_val'],
        'discount' => $estimate['discount'],
        'customer_id' => $estimate['customer_id'],
        'notes' => $estimate['notes'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'tax_type_id' => $estimate['taxes'][0]['tax_type_id'],
        'amount' => $estimate['tax'],
    ]);

    $this->assertDatabaseHas('estimate_items', [
        'item_id' => $estimate['items'][0]['item_id'],
        'name' => $estimate['items'][0]['name'],
    ]);
});

test('update estimate with EUR currency', function () {
    $estimate = Estimate::factory()
        ->hasItems(1)
        ->hasTaxes(1)
        ->create([
            'estimate_date' => '1988-07-18',
            'expiry_date' => '1988-08-18',
        ]);

    $estimate2 = Estimate::factory()
        ->raw([
            'id' => $estimate->id,
            'discount_type' => 'fixed',
            'discount_val' => 20,
            'sub_total' => 200,
            'total' => 189,
            'tax' => 9,
            'exchange_rate' => 86.403538,
            'base_discount_val' => 1728,
            'base_sub_total' => 17281,
            'base_total' => 16330,
            'base_tax' => 778,
            'taxes' => [Tax::factory()->raw([
                'tax_type_id' => $estimate->taxes[0]->tax_type_id,
                'amount' => 9,
                'percent' => 5,
                'exchange_rate' => 86.403538,
                'base_amount' => 778,
            ])],
            'items' => [EstimateItem::factory()->raw([
                'estimate_id' => $estimate->id,
                'discount_type' => 'fixed',
                'quantity' => 1,
                'discount' => 0,
                'discount_val' => 0,
                'price' => 200,
                'tax' => 0,
                'total' => 200,
                'exchange_rate' => 86.403538,
                'base_discount_val' => 0,
                'base_price' => 17281,
                'base_tax' => 0,
                'base_total' => 17281,
            ])],
        ]);

    $response = putJson('api/v1/estimates/'.$estimate->id, $estimate2);

    $this->assertDatabaseHas('estimates', [
        'id' => $estimate['id'],
        'template_name' => $estimate2['template_name'],
        'estimate_number' => $estimate2['estimate_number'],
        'discount_type' => $estimate2['discount_type'],
        'discount_val' => $estimate2['discount_val'],
        'sub_total' => $estimate2['sub_total'],
        'discount' => $estimate2['discount'],
        'customer_id' => $estimate2['customer_id'],
        'total' => $estimate2['total'],
        'tax' => $estimate2['tax'],
        'exchange_rate' => $estimate2['exchange_rate'],
        'base_discount_val' => $estimate2['base_discount_val'],
        'base_sub_total' => $estimate2['base_sub_total'],
        'base_total' => $estimate2['base_total'],
        'base_tax' => $estimate2['base_tax'],
    ]);

    $this->assertDatabaseHas('estimate_items', [
        'estimate_id' => $estimate2['items'][0]['estimate_id'],
        'exchange_rate' => $estimate2['items'][0]['exchange_rate'],
        'base_price' => $estimate2['items'][0]['base_price'],
        'base_discount_val' => $estimate2['items'][0]['base_discount_val'],
        'base_tax' => $estimate2['items'][0]['base_tax'],
        'base_total' => $estimate2['items'][0]['base_total'],
    ]);

    $response->assertStatus(200);
});

test('create estimate with tax included', function () {
    $estimate = Estimate::factory()->raw([
        'estimate_number' => 'EST-000006',
        'items' => [
            EstimateItem::factory()->raw(),
        ],
        'taxes' => [
            Tax::factory()->raw(),
        ],
        'tax_included' => true,
    ]);

    postJson('api/v1/estimates', $estimate)
        ->assertStatus(201);

    $this->assertDatabaseHas('estimates', [
        'tax_included' => $estimate['tax_included'],
    ]);
});
