<?php

use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceController;
use App\Http\Controllers\Company\RecurringInvoice\RecurringInvoiceFrequencyController;
use App\Http\Requests\RecurringInvoiceFrequencyRequest;
use App\Http\Requests\RecurringInvoiceRequest;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use App\Models\User;
use Carbon\Carbon;
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

test('get recurring invoices', function () {
    RecurringInvoice::factory()->create();

    getJson('api/v1/recurring-invoices?page=1')
        ->assertOk();
});

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'store',
        RecurringInvoiceRequest::class
    );
});

test('store recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertStatus(201);

    $recurringInvoice = collect($recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $recurringInvoice);
});

test('get recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->create();

    getJson("api/v1/recurring-invoices/{$recurringInvoice->id}")
        ->assertOk();
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'update',
        RecurringInvoiceRequest::class
    );
});

test('update recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->create();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    $new_recurringInvoice = RecurringInvoice::factory()->raw();
    $new_recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    putJson("api/v1/recurring-invoices/{$recurringInvoice->id}", $new_recurringInvoice)
        ->assertOk();

    $new_recurringInvoice = collect($new_recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $new_recurringInvoice);
});

test('delete multiple recurring invoice', function () {
    $recurringInvoices = RecurringInvoice::factory()->count(3)->create();

    $data = [
        'ids' => $recurringInvoices->pluck('id'),
    ];

    postJson('api/v1/recurring-invoices/delete', $data)
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    foreach ($recurringInvoices as $recurringInvoice) {
        $this->assertModelMissing($recurringInvoice);
    }
});

test('calculate frequency for recurring invoice', function () {
    $data = [
        'frequency' => '* * 2 * *',
        'starts_at' => Carbon::now()->format('Y-m-d'),
    ];

    $queryString = http_build_query($data, '', '&');

    getJson('api/v1/recurring-invoice-frequency?'.$queryString)
        ->assertOk();
});

test('frequency preview uses a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceFrequencyController::class,
        '__invoke',
        RecurringInvoiceFrequencyRequest::class
    );
});

test('invalid frequency previews return validation errors', function () {
    $queryString = http_build_query([
        'frequency' => 'not a cron expression',
        'starts_at' => Carbon::now()->format('Y-m-d'),
    ], '', '&');

    getJson('api/v1/recurring-invoice-frequency?'.$queryString)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('frequency');
});

test('invalid recurring invoice cron expressions are rejected', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw(['frequency' => 'not a cron expression']);
    $recurringInvoice['items'] = [InvoiceItem::factory()->raw()];

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('frequency');
});

test('creating a recurring invoice with a past start uses the next future occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));

    try {
        CompanySetting::setSettings(['time_zone' => 'UTC'], User::findOrFail(1)->companies()->firstOrFail()->id);

        $recurringInvoice = RecurringInvoice::factory()->raw([
            'starts_at' => '2026-07-01 00:00:00',
            'frequency' => '0 0 * * *',
            'status' => RecurringInvoice::ACTIVE,
        ]);
        $recurringInvoice['items'] = [InvoiceItem::factory()->raw()];

        $response = postJson('api/v1/recurring-invoices', $recurringInvoice)
            ->assertCreated();

        $this->assertDatabaseHas('recurring_invoices', [
            'id' => $response->json('data.id'),
            'next_invoice_at' => '2026-08-03 00:00:00',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

test('reactivating a past recurring invoice starts from the next future occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));

    try {
        $companyId = User::findOrFail(1)->companies()->firstOrFail()->id;
        CompanySetting::setSettings(['time_zone' => 'UTC'], $companyId);
        $recurringInvoice = RecurringInvoice::factory()->create([
            'status' => RecurringInvoice::ON_HOLD,
            'starts_at' => '2026-07-01 00:00:00',
            'frequency' => '0 0 * * *',
            'next_invoice_at' => '2026-07-02 00:00:00',
        ]);
        $payload = RecurringInvoice::factory()->raw([
            'status' => RecurringInvoice::ACTIVE,
            'starts_at' => '2026-07-01 00:00:00',
            'frequency' => '0 0 * * *',
        ]);
        $payload['items'] = [InvoiceItem::factory()->raw()];

        putJson("api/v1/recurring-invoices/{$recurringInvoice->id}", $payload)
            ->assertOk();

        $this->assertDatabaseHas('recurring_invoices', [
            'id' => $recurringInvoice->id,
            'status' => RecurringInvoice::ACTIVE,
            'next_invoice_at' => '2026-08-03 00:00:00',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

test('the dispatcher ignores non-active templates and does not duplicate an occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));

    try {
        $active = RecurringInvoice::factory()->create([
            'status' => RecurringInvoice::ACTIVE,
            'frequency' => '* * * * *',
            'limit_by' => RecurringInvoice::NONE,
            'next_invoice_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        $onHold = RecurringInvoice::factory()->create([
            'status' => RecurringInvoice::ON_HOLD,
            'frequency' => '* * * * *',
            'limit_by' => RecurringInvoice::NONE,
            'next_invoice_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        Artisan::call('generate:recurring-invoices');
        Artisan::call('generate:recurring-invoices');

        expect(Invoice::where('recurring_invoice_id', $active->id)->count())->toBe(1)
            ->and(Invoice::where('recurring_invoice_id', $onHold->id)->count())->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});
