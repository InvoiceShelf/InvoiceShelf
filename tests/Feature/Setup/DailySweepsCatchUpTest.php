<?php

use App\Domains\Sales\Models\Invoice;
use App\Platform\Operations\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
 * The daily status sweeps (overdue invoices, expired estimates) run through
 * `invoiceshelf:catch-up`, once a day at the first chance, so an install that
 * was down at midnight does not skip a day.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

function overdueCandidate(): Invoice
{
    return Invoice::factory()->create([
        'status' => Invoice::STATUS_SENT,
        'due_date' => now()->subDays(3)->toDateString(),
        'overdue' => false,
    ]);
}

test('it runs the sweeps and remembers the day', function () {
    $invoice = overdueCandidate();

    expect(Artisan::call('invoiceshelf:catch-up'))->toBe(0)
        ->and($invoice->fresh()->overdue)->toBeTruthy()
        ->and(Setting::getSetting('daily_sweeps_ran_on'))->toBe(now()->toDateString());
});

test('it runs once a day unless forced', function () {
    Artisan::call('invoiceshelf:catch-up');
    $later = overdueCandidate();

    Artisan::call('invoiceshelf:catch-up');
    expect($later->fresh()->overdue)->toBeFalsy();

    Artisan::call('invoiceshelf:catch-up', ['--force' => true]);
    expect($later->fresh()->overdue)->toBeTruthy();
});

test('it runs again on the next day', function () {
    Artisan::call('invoiceshelf:catch-up');
    $this->travel(1)->days();
    $invoice = overdueCandidate();

    Artisan::call('invoiceshelf:catch-up');

    expect($invoice->fresh()->overdue)->toBeTruthy()
        ->and(Setting::getSetting('daily_sweeps_ran_on'))->toBe(now()->toDateString());
});

test('the scheduler asks for it every hour instead of once at midnight', function () {
    $events = collect(app(Schedule::class)->events())->mapWithKeys(
        fn ($event) => [trim(str_replace(["'", '"'], '', explode('artisan', $event->command)[1] ?? '')) => $event->expression]
    );

    expect($events->get('invoiceshelf:catch-up'))->toBe('0 * * * *')
        ->and($events->has('check:invoices:status'))->toBeFalse()
        ->and($events->has('check:estimates:status'))->toBeFalse();
});
