<?php

use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('the next run no longer collapses back to the start date', function () {
    // It used to be recomputed from starts_at on every call, so the column was
    // pinned to the first occurrence after the schedule began and the date on
    // the schedule screen stopped being true after the first invoice.
    $recurringInvoice = RecurringInvoice::factory()->create([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'starts_at' => Carbon::now()->subYear(),
    ]);

    $recurringInvoice->updateNextInvoiceDate();

    $next = Carbon::parse($recurringInvoice->fresh()->next_invoice_at);

    expect($next->greaterThan(Carbon::now()))->toBeTrue()
        ->and($next->lessThan(Carbon::now()->addYear()))->toBeTrue();
});

test('the next run is stable when called repeatedly', function () {
    $recurringInvoice = RecurringInvoice::factory()->create([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'starts_at' => Carbon::now()->subYear(),
    ]);

    $recurringInvoice->updateNextInvoiceDate();
    $first = $recurringInvoice->fresh()->next_invoice_at;

    $recurringInvoice->updateNextInvoiceDate();

    expect($recurringInvoice->fresh()->next_invoice_at)->toBe($first);
});

test('a schedule that has not started yet counts from its start date', function () {
    $recurringInvoice = RecurringInvoice::factory()->create([
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'starts_at' => Carbon::now()->addMonth(),
    ]);

    $recurringInvoice->updateNextInvoiceDate();

    $next = Carbon::parse($recurringInvoice->fresh()->next_invoice_at);

    expect($next->greaterThanOrEqualTo(Carbon::parse($recurringInvoice->starts_at)))->toBeTrue();
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
    // The key was deleted from config/services.php in a cleanup and nothing
    // noticed, because no test covered the wiring. This is that test.
    $configuration = require config_path('services.php');

    expect($configuration)->toHaveKey('cron_job');
});

test('the cron webhook runs the scheduler once a minute however often it is called', function () {
    // Laravel decides what is due from the current minute, so a second run in
    // the same minute would generate the same recurring invoice twice.
    config(['services.cron_job.auth_token' => 'a-real-token']);

    $this->withHeaders(['x-authorization-token' => 'a-real-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true, 'ran' => true]);

    $this->withHeaders(['x-authorization-token' => 'a-real-token'])
        ->getJson('/api/cron')->assertOk()->assertJson(['success' => true, 'ran' => false]);
});
