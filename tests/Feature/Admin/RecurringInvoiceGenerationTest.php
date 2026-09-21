<?php

use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\artisan;
use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

/**
 * A schedule that fell due before now, with the date already written the way
 * the scheduler writes it.
 */
function dueRecurringInvoice(array $attributes = []): RecurringInvoice
{
    return RecurringInvoice::factory()->create(array_merge([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'limit_by' => RecurringInvoice::NONE,
        'starts_at' => Carbon::now()->subMonth(),
        'next_invoice_at' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
    ], $attributes));
}

test('a schedule that has fallen due is billed once', function () {
    $recurringInvoice = dueRecurringInvoice();

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(1);
});

test('running twice in the same minute bills once', function () {
    $recurringInvoice = dueRecurringInvoice();

    artisan('recurring-invoices:generate')->assertSuccessful();
    artisan('recurring-invoices:generate')->assertSuccessful();
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(1);
});

test('a schedule that is not due yet is left alone', function () {
    $recurringInvoice = dueRecurringInvoice([
        'next_invoice_at' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
    ]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(0);
});

test('a schedule that is not active is left alone', function () {
    $recurringInvoice = dueRecurringInvoice(['status' => RecurringInvoice::ON_HOLD]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(0);
});

test('billing moves the next run into the future', function () {
    $recurringInvoice = dueRecurringInvoice();

    artisan('recurring-invoices:generate')->assertSuccessful();

    $next = Carbon::parse($recurringInvoice->fresh()->next_invoice_at);

    expect($next->greaterThan(Carbon::now()))->toBeTrue();
});

test('the next run no longer collapses back to the start date', function () {
    // The old implementation recomputed from starts_at every time, so the
    // column was pinned to the first occurrence after the schedule began.
    $recurringInvoice = dueRecurringInvoice([
        'starts_at' => Carbon::now()->subYear(),
    ]);

    $recurringInvoice->updateNextInvoiceDate();

    $next = Carbon::parse($recurringInvoice->fresh()->next_invoice_at);

    expect($next->greaterThan(Carbon::now()))->toBeTrue()
        ->and($next->lessThan(Carbon::now()->addYear()))->toBeTrue();
});

test('a schedule that has not started yet counts from its start date', function () {
    $recurringInvoice = dueRecurringInvoice([
        'starts_at' => Carbon::now()->addMonth(),
    ]);

    $recurringInvoice->updateNextInvoiceDate();

    $next = Carbon::parse($recurringInvoice->fresh()->next_invoice_at);

    expect($next->greaterThanOrEqualTo(Carbon::parse($recurringInvoice->starts_at)))->toBeTrue();
});

test('a count limit still completes the schedule', function () {
    $recurringInvoice = dueRecurringInvoice([
        'limit_by' => RecurringInvoice::COUNT,
        'limit_count' => 1,
    ]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    // The first pass bills; the second finds the limit reached and retires it.
    // Re-armed through the query builder, because a model update compares
    // against the value this instance was loaded with, not the stored one.
    RecurringInvoice::whereKey($recurringInvoice->id)
        ->update(['next_invoice_at' => Carbon::now()->subHour()->format('Y-m-d H:i:s')]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(1)
        ->and($recurringInvoice->fresh()->status)->toBe(RecurringInvoice::COMPLETED);
});

test('a date limit in the past retires the schedule without billing', function () {
    $recurringInvoice = dueRecurringInvoice([
        'limit_by' => RecurringInvoice::DATE,
        'limit_date' => Carbon::now()->subWeek()->format('Y-m-d'),
    ]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count())->toBe(0)
        ->and($recurringInvoice->fresh()->status)->toBe(RecurringInvoice::COMPLETED);
});

test('the cron webhook refuses every caller when no token is configured', function () {
    config(['services.cron_job.auth_token' => null]);

    getJson('/api/cron')->assertUnauthorized();

    $this->withHeaders(['x-authorization-token' => 'anything'])
        ->getJson('/api/cron')->assertUnauthorized();
});

test('the cron webhook accepts only the configured token', function () {
    config(['services.cron_job.auth_token' => 'a-real-token']);

    getJson('/api/cron')->assertUnauthorized();

    $this->withHeaders(['x-authorization-token' => 'wrong'])
        ->getJson('/api/cron')->assertUnauthorized();

    $this->withHeaders(['x-authorization-token' => 'a-real-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true]);
});

test('the configured cron token is read from the environment', function () {
    // The key went missing from config/services.php once already, and the only
    // test covering the middleware injected the value itself, so nothing
    // noticed. This asserts the wiring rather than the guard.
    $configuration = require config_path('services.php');

    expect($configuration)->toHaveKey('cron_job');
});

test('the cron webhook runs the scheduler once a minute however often it is called', function () {
    // Laravel decides what is due from the current minute, so a second run
    // inside the same minute would bill the same schedule again on any branch
    // whose generation is not itself idempotent.
    config(['services.cron_job.auth_token' => 'a-real-token']);

    $this->withHeaders(['x-authorization-token' => 'a-real-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true, 'ran' => true]);

    $this->withHeaders(['x-authorization-token' => 'a-real-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true, 'ran' => false]);
});
