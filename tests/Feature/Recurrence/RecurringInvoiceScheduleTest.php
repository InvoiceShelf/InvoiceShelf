<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\DocumentItemService;
use App\Domains\Sales\Application\RecurringInvoiceService;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\artisan;

/**
 * Recurring invoices on the shared recurrence runner: dated their scheduled
 * day where the company is, generated and moved on in one transaction.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);

    // Midday in UTC is already the next hour in Skopje (UTC+2 in summer).
    Carbon::setTestNow('2026-06-15 12:00:00');
    CompanySetting::setSettings(['time_zone' => 'Europe/Skopje'], $this->companyId);
});

afterEach(fn () => Carbon::setTestNow());

function scheduledInvoice(int $companyId, array $attributes = []): RecurringInvoice
{
    return RecurringInvoice::factory()->create([
        'company_id' => $companyId,
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'limit_by' => RecurringInvoice::NONE,
        'starts_at' => '2026-06-01 00:00:00',
        ...$attributes,
    ]);
}

test('a schedule that missed runs sends one invoice dated the latest of them', function () {
    // Midnight in Skopje on 13 June, as the scheduler stores it (UTC).
    $schedule = scheduledInvoice($this->companyId, ['next_invoice_at' => '2026-06-12 22:00:00']);

    artisan('recurring-invoices:generate')->assertSuccessful();

    $invoices = Invoice::query()->where('recurring_invoice_id', $schedule->id)->get();

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->invoice_date)->toBe('2026-06-15')
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-06-15 22:00:00');
});

test('a failed run keeps its occurrence and is not retried every minute', function () {
    $schedule = scheduledInvoice($this->companyId, ['next_invoice_at' => '2026-06-14 22:00:00']);
    $this->mock(DocumentItemService::class, fn ($mock) => $mock->shouldReceive('createItems')->andThrow(new RuntimeException('Broken template')));

    artisan('recurring-invoices:generate')->assertSuccessful();

    // The invoice written before the failure was rolled back with it.
    expect(Invoice::query()->where('recurring_invoice_id', $schedule->id)->count())->toBe(0)
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-06-14 22:00:00');

    Carbon::setTestNow('2026-06-15 12:30:00');
    expect(app(RecurringInvoiceService::class)->generateDue())->toBe(0);
});

test('the frequency preview works in the company time zone and lists the next runs', function () {
    $this->getJson('/api/v1/recurring-invoice-frequency?frequency=0 0 1 * *&starts_at=2026-06-15')
        ->assertOk()
        ->assertJsonPath('next_invoice_at', '2026-06-30 22:00:00')
        ->assertJsonPath('upcoming', ['2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01']);
});

test('the frequency preview counts from now when the form has no start date yet', function () {
    Carbon::setTestNow('2026-06-15 12:30:00');

    $this->getJson('/api/v1/recurring-invoice-frequency?frequency=0 0 1 * *')
        ->assertOk()
        ->assertJsonPath('upcoming.0', '2026-07-01');
});

test('a frequency the scheduler cannot read is a validation error', function () {
    $this->getJson('/api/v1/recurring-invoice-frequency?frequency=every monday&starts_at=2026-06-15')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('frequency');

    $this->postJson('/api/v1/recurring-invoices', ['frequency' => 'every monday'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('frequency');
});

function recurringInvoicePayload(RecurringInvoice $schedule, array $overrides = []): array
{
    return [
        ...$schedule->only(['customer_id', 'discount_type', 'discount', 'discount_val', 'template_name', 'exchange_rate', 'currency_id']),
        'starts_at' => '2026-06-01',
        'frequency' => $schedule->frequency,
        'limit_by' => 'NONE',
        'status' => 'ACTIVE',
        'send_automatically' => false,
        'items' => [['name' => 'Retainer', 'quantity' => 1, 'price' => 1000, 'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1000]],
        'taxes' => [],
        'sub_total' => 1000,
        'total' => 1000,
        'tax' => 0,
        ...$overrides,
    ];
}

test('saving a schedule works out its first run in the company time zone', function () {
    $schedule = scheduledInvoice($this->companyId);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, recurringInvoicePayload($schedule, ['starts_at' => '2026-06-20']))
        ->assertSuccessful();

    // Midnight on 21 June in Skopje, not in UTC.
    expect($schedule->fresh()->next_invoice_at)->toBe('2026-06-20 22:00:00');
});

test('editing a schedule keeps its next run, so the period already billed is not billed again', function () {
    $schedule = scheduledInvoice($this->companyId, ['frequency' => '0 0 1 * *', 'next_invoice_at' => '2026-05-31 22:00:00']);
    artisan('recurring-invoices:generate')->assertSuccessful();
    expect($schedule->fresh()->next_invoice_at)->toBe('2026-06-30 22:00:00');

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, recurringInvoicePayload($schedule->fresh(), [
        'items' => [['name' => 'Retainer (fixed typo)', 'quantity' => 1, 'price' => 1000, 'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1000]],
    ]))->assertSuccessful();
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect($schedule->fresh()->next_invoice_at)->toBe('2026-06-30 22:00:00')
        ->and(Invoice::query()->where('recurring_invoice_id', $schedule->id)->count())->toBe(1);
});

test('changing the frequency counts the next run from now', function () {
    $schedule = scheduledInvoice($this->companyId, ['frequency' => '0 0 1 * *', 'next_invoice_at' => '2026-06-30 22:00:00']);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, recurringInvoicePayload($schedule, ['frequency' => '0 0 * * 1']))
        ->assertSuccessful();

    // The Monday after 15 June, midnight in Skopje.
    expect($schedule->fresh()->next_invoice_at)->toBe('2026-06-21 22:00:00');
});

test('a sub-daily schedule far behind still sends a single invoice', function () {
    $schedule = scheduledInvoice($this->companyId, ['frequency' => '* * * * *', 'next_invoice_at' => '2026-05-15 12:00:00']);

    artisan('recurring-invoices:generate')->assertSuccessful();
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::query()->where('recurring_invoice_id', $schedule->id)->count())->toBe(1)
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-06-15 12:01:00');
});

test('a start date is read as that day where the company is', function () {
    CompanySetting::setSettings(['time_zone' => 'America/New_York'], $this->companyId);

    $this->getJson('/api/v1/recurring-invoice-frequency?frequency=0 0 * * *&starts_at=2026-09-30')
        ->assertOk()
        ->assertJsonPath('upcoming.0', '2026-10-01');
});

test('a daily invoice at a time the clocks repeat is sent once that day', function () {
    // 02:30 in Skopje happens twice on 25 October 2026: at 00:30 and 01:30 UTC.
    $schedule = scheduledInvoice($this->companyId, ['frequency' => '30 2 * * *', 'next_invoice_at' => '2026-10-25 00:30:00']);

    Carbon::setTestNow('2026-10-25 00:31:00');
    artisan('recurring-invoices:generate')->assertSuccessful();
    Carbon::setTestNow('2026-10-25 01:31:00');
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::query()->where('recurring_invoice_id', $schedule->id)->count())->toBe(1)
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-10-26 01:30:00');
});
