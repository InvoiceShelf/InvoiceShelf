<?php

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);
});

/**
 * An active schedule whose next run passed a minute ago. Its expression, once
 * a year at midnight on 1 January, does not match the current minute.
 */
function dueRecurringInvoice(array $attributes = []): RecurringInvoice
{
    return RecurringInvoice::factory()->create(array_merge([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 1 1 *',
        'starts_at' => Carbon::now()->subYears(2)->toDateTimeString(),
        'limit_by' => RecurringInvoice::NONE,
        'send_automatically' => false,
        'next_invoice_at' => Carbon::now()->subMinute()->toDateTimeString(),
    ], $attributes));
}

function invoicesFrom(RecurringInvoice $schedule): int
{
    return Invoice::where('recurring_invoice_id', $schedule->id)->count();
}

test('a due schedule is billed although its expression does not match the current minute', function () {
    // The per-schedule cron entry this replaces billed it only when the
    // scheduler happened to run at midnight on 1 January.
    $schedule = dueRecurringInvoice();

    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(1)
        ->and(Carbon::parse($schedule->fresh()->next_invoice_at)->isFuture())->toBeTrue();
});

test('a gap of missed runs bills one invoice, not one per missed run', function () {
    $schedule = dueRecurringInvoice([
        'frequency' => '0 0 * * *',
        'next_invoice_at' => Carbon::now()->subDays(10)->toDateTimeString(),
    ]);

    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(1)
        ->and(Carbon::parse($schedule->fresh()->next_invoice_at)->isFuture())->toBeTrue();
});

test('running again before the next run bills nothing more', function () {
    $schedule = dueRecurringInvoice();

    Artisan::call('recurring-invoices:generate');
    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(1);
});

test('schedules that are not due, on hold or completed are not billed', function () {
    $notDue = dueRecurringInvoice(['next_invoice_at' => Carbon::now()->addDay()->toDateTimeString()]);
    $onHold = dueRecurringInvoice(['status' => RecurringInvoice::ON_HOLD]);
    $completed = dueRecurringInvoice(['status' => RecurringInvoice::COMPLETED]);

    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($notDue) + invoicesFrom($onHold) + invoicesFrom($completed))->toBe(0);
});

test('a schedule at its count limit is completed instead of billed', function () {
    $schedule = dueRecurringInvoice(['limit_by' => RecurringInvoice::COUNT, 'limit_count' => 1]);

    Artisan::call('recurring-invoices:generate');
    $schedule->fresh()->update(['next_invoice_at' => Carbon::now()->subMinute()->toDateTimeString()]);
    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(1)
        ->and($schedule->fresh()->status)->toBe(RecurringInvoice::COMPLETED);
});

test('a failed mail keeps the invoice as a draft and moves the schedule on', function () {
    Exceptions::fake();
    $schedule = dueRecurringInvoice(['send_automatically' => true]);
    CompanySetting::setSettings(['invoice_email_attachment' => 'NO'], $schedule->company_id);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

    Artisan::call('recurring-invoices:generate');

    $invoice = Invoice::where('recurring_invoice_id', $schedule->id)->sole();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and(Carbon::parse($schedule->fresh()->next_invoice_at)->isFuture())->toBeTrue();
    Exceptions::assertReported(RuntimeException::class);

    // Billed once: the mail failure did not leave the schedule due.
    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(1);
});

test('a schedule that fails is retried hourly, not on every run', function () {
    Exceptions::fake();
    // An expression that no longer parses fails after the invoice is created,
    // so the whole occurrence rolls back.
    $schedule = dueRecurringInvoice(['frequency' => 'not a cron expression']);

    Artisan::call('recurring-invoices:generate');
    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(0);
    Exceptions::assertReportedCount(1);

    $this->travel(61)->minutes();
    Artisan::call('recurring-invoices:generate');

    Exceptions::assertReportedCount(2);
});

test('the frequency preview counts a long-running schedule from now', function () {
    $query = http_build_query([
        'frequency' => '0 0 * * *',
        'starts_at' => Carbon::now()->subYear()->format('Y-m-d'),
    ]);

    $next = getJson("api/v1/recurring-invoice-frequency?{$query}")
        ->assertOk()
        ->json('next_invoice_at');

    expect(Carbon::parse($next)->isFuture())->toBeTrue();
});

test('editing a schedule that started long ago does not make it due', function () {
    // Saving used to count the next run from the start date, which for a
    // schedule a year old is in the past, and so billed again at once.
    $schedule = RecurringInvoice::factory()->create(['status' => RecurringInvoice::ACTIVE]);

    $payload = RecurringInvoice::factory()->raw([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'starts_at' => Carbon::now()->subYear()->format('Y-m-d'),
        'limit_by' => RecurringInvoice::NONE,
    ]);
    $payload['items'] = [InvoiceItem::factory()->raw()];

    putJson("api/v1/recurring-invoices/{$schedule->id}", $payload)->assertOk();

    expect($schedule->fresh()->isDue())->toBeFalse();

    Artisan::call('recurring-invoices:generate');

    expect(invoicesFrom($schedule))->toBe(0);
});
