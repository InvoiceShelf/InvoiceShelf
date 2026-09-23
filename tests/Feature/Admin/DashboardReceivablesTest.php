<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Carbon::setTestNow('2026-06-15 10:00:00');

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * An issued invoice owing $baseDue in the company currency, due on $dueDate.
 */
function dashboardReceivable(?string $dueDate, int $baseDue, array $overrides = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'status' => Invoice::STATUS_SENT,
        'type' => Invoice::TYPE_INVOICE,
        'due_date' => $dueDate,
        'due_amount' => $baseDue,
        'base_due_amount' => $baseDue,
    ], $overrides));
}

function dashboardReceivables(): array
{
    return getJson('api/v1/dashboard')->assertOk()->json('receivables');
}

test('the dashboard splits what is owed into overdue, due soon and due later', function () {
    $before = dashboardReceivables();

    dashboardReceivable('2026-06-14', 1000);              // yesterday: overdue
    dashboardReceivable('2026-03-01', 2500);              // months late: overdue
    dashboardReceivable('2026-06-15', 400);               // due today: not overdue yet
    dashboardReceivable('2026-07-15', 700);               // day 30: still "soon"
    dashboardReceivable('2026-07-16', 900);               // day 31: later
    dashboardReceivable(null, 300);                       // no due date: later

    $after = dashboardReceivables();

    expect($after['overdue'] - $before['overdue'])->toBe(3500)
        ->and($after['overdue_count'] - $before['overdue_count'])->toBe(2)
        ->and($after['due_soon'] - $before['due_soon'])->toBe(1100)
        ->and($after['due_later'] - $before['due_later'])->toBe(1200)
        ->and($after['outstanding'] - $before['outstanding'])->toBe(5800)
        ->and($after['outstanding_count'] - $before['outstanding_count'])->toBe(6);
});

test('drafts, settled invoices and credit notes are not receivables', function () {
    $before = dashboardReceivables();

    dashboardReceivable('2026-06-01', 5000, ['status' => Invoice::STATUS_DRAFT]);
    dashboardReceivable('2026-06-01', 0, ['paid_status' => Invoice::STATUS_PAID]);
    dashboardReceivable('2026-06-01', 800, ['type' => Invoice::TYPE_CREDIT_NOTE]);

    expect(dashboardReceivables())->toEqual($before);
});

test('a partly paid invoice counts what is still owed, in the company currency', function () {
    $before = dashboardReceivables();

    // 1,000.00 in the customer's currency at a rate of 1.25 leaves 1,250.00
    // owed in the company's; the dashboard speaks the company's currency.
    dashboardReceivable('2026-06-01', 125000, [
        'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
        'due_amount' => 100000,
        'exchange_rate' => 1.25,
    ]);

    $after = dashboardReceivables();

    expect($after['overdue'] - $before['overdue'])->toBe(125000);
});

test('another company\'s invoices stay out of the figures', function () {
    $before = dashboardReceivables();

    $other = Company::factory()->create();
    dashboardReceivable('2026-06-01', 9000, ['company_id' => $other->id]);

    expect(dashboardReceivables())->toEqual($before);
});
