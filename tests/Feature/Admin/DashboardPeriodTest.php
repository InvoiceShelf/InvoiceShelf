<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    // The demo company's fiscal year is the calendar year
    Carbon::setTestNow('2026-06-15 10:00:00');

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function periodInvoice(string $date, int $baseTotal): Invoice
{
    return Invoice::factory()->create(['invoice_date' => $date, 'base_total' => $baseTotal]);
}

function periodExpense(string $date, int $baseAmount): Expense
{
    return Expense::factory()->create(['expense_date' => $date, 'base_amount' => $baseAmount]);
}

function periodPayment(string $date, int $baseAmount): Payment
{
    return Payment::factory()->create(['payment_date' => $date, 'base_amount' => $baseAmount]);
}

/**
 * The dashboard's money figures, with each series given as the change a
 * test's own documents made.
 */
function periodDashboard(array $query = []): array
{
    return getJson('api/v1/dashboard?'.http_build_query($query))->assertOk()->json();
}

function periodDelta(array $after, array $before, string $series): array
{
    return array_map(
        fn ($a, $b) => (int) $a - (int) $b,
        $after['chart_data'][$series],
        $before['chart_data'][$series],
    );
}

test('the default window is the fiscal year, twelve months labelled by name', function () {
    $before = periodDashboard();

    periodInvoice('2026-02-10', 1000);
    periodExpense('2026-02-20', 300);
    periodPayment('2026-02-25', 400);
    periodInvoice('2025-12-31', 5000);  // the day before the window opens

    $after = periodDashboard();

    expect($after['chart_data']['months'])
        ->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])
        ->and(periodDelta($after, $before, 'invoice_totals')[1])->toBe(1000)
        ->and(periodDelta($after, $before, 'expense_totals')[1])->toBe(300)
        ->and(periodDelta($after, $before, 'receipt_totals')[1])->toBe(400)
        ->and(periodDelta($after, $before, 'net_income_totals')[1])->toBe(100)
        ->and($after['total_sales'] - $before['total_sales'])->toBe(1000)
        ->and($after['total_net_income'] - $before['total_net_income'])->toBe(100)
        ->and($after['period'])->toBe(['from' => '2026-01-01', 'to' => '2026-12-31', 'granularity' => 'month']);
});

test('previous_year moves the whole window back a year', function () {
    $before = periodDashboard(['previous_year' => 1]);

    periodInvoice('2025-03-05', 700);
    periodInvoice('2026-03-05', 900);   // this year's window, not last year's

    $after = periodDashboard(['previous_year' => 1]);

    expect(periodDelta($after, $before, 'invoice_totals')[2])->toBe(700)
        ->and($after['total_sales'] - $before['total_sales'])->toBe(700)
        ->and($after['period']['from'])->toBe('2025-01-01')
        ->and($after['period']['to'])->toBe('2025-12-31');
});

test('a custom range is bucketed by month and clipped at both ends', function () {
    $query = ['from_date' => '2026-01-15', 'to_date' => '2026-04-10'];
    $before = periodDashboard($query);

    periodInvoice('2026-01-14', 111);   // a day before the range
    periodInvoice('2026-01-15', 200);   // first day
    periodInvoice('2026-03-31', 300);
    periodInvoice('2026-04-10', 400);   // last day
    periodInvoice('2026-04-11', 555);   // a day after

    $after = periodDashboard($query);

    expect($after['chart_data']['months'])->toBe(['Jan', 'Feb', 'Mar', 'Apr'])
        ->and(periodDelta($after, $before, 'invoice_totals'))->toBe([200, 0, 300, 400])
        ->and($after['total_sales'] - $before['total_sales'])->toBe(900)
        ->and($after['period'])->toBe(['from' => '2026-01-15', 'to' => '2026-04-10', 'granularity' => 'month']);
});

test('a range of up to about two months is bucketed by day', function () {
    $query = ['from_date' => '2026-05-01', 'to_date' => '2026-05-20'];
    $before = periodDashboard($query);

    periodPayment('2026-05-03', 250);

    $after = periodDashboard($query);

    expect($after['chart_data']['months'])->toHaveCount(20)
        ->and($after['chart_data']['months'][0])->toBe('1 May')
        ->and(periodDelta($after, $before, 'receipt_totals')[2])->toBe(250)
        ->and($after['period']['granularity'])->toBe('day');
});

test('a range longer than a year names the year with each month', function () {
    $response = periodDashboard(['from_date' => '2025-01-01', 'to_date' => '2026-02-28']);

    expect($response['chart_data']['months'])->toHaveCount(14)
        ->and($response['chart_data']['months'][0])->toBe('Jan 25')
        ->and($response['chart_data']['months'][13])->toBe('Feb 26');
});

test('dates take precedence over previous_year', function () {
    $response = periodDashboard(['previous_year' => 1, 'from_date' => '2026-01-01', 'to_date' => '2026-03-31']);

    expect($response['period']['from'])->toBe('2026-01-01')
        ->and($response['chart_data']['months'])->toHaveCount(3);
});

test('a malformed range is refused', function (array $query, string $field) {
    getJson('api/v1/dashboard?'.http_build_query($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'reversed' => [['from_date' => '2026-03-01', 'to_date' => '2026-02-01'], 'to_date'],
    'start only' => [['from_date' => '2026-03-01'], 'to_date'],
    'end only' => [['to_date' => '2026-03-01'], 'from_date'],
    'wrong format' => [['from_date' => '01/03/2026', 'to_date' => '2026-03-31'], 'from_date'],
    'over five years' => [['from_date' => '2020-01-01', 'to_date' => '2026-01-02'], 'to_date'],
]);
