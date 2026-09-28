<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Application\RecurringCostService;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => purchaseFixtures($this));
afterEach(fn () => CarbonImmutable::setTestNow());

function recurringPayload($test): array
{
    return ['name' => 'Monthly hosting', 'supplier_id' => $test->supplier->id, 'mode' => 'BILL', 'frequency' => 'MONTH', 'interval' => 1, 'starts_at' => '2026-01-31', 'ends_at' => null, 'max_occurrences' => 3, 'due_days' => 14, 'auto_record_paid' => false, 'template' => purchaseBillPayload($test, 2500)];
}

test('monthly costs catch up once per occurrence and retain their month end anchor', function () {
    CarbonImmutable::setTestNow('2026-04-01 12:00:00');
    $service = app(RecurringCostService::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, recurringPayload($this));
    expect($service->generate($schedule))->toBe(3)->and($service->generate($schedule))->toBe(0);
    expect(Bill::orderBy('document_date')->pluck('document_date')->all())->toBe(['2026-01-31', '2026-02-28', '2026-03-31'])
        ->and($schedule->fresh()->status)->toBe('COMPLETED')
        ->and((int) Bill::sum('due_amount'))->toBe(7500);
});

test('failed generation does not advance the occurrence and retries after correction', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');
    $service = app(RecurringCostService::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, recurringPayload($this));
    $this->supplier->update(['enabled' => false]);
    expect($service->generate($schedule))->toBe(0)->and($schedule->fresh()->next_run_at)->toBe('2026-01-31')->and($schedule->fresh()->last_error)->not->toBeNull();
    $this->supplier->update(['enabled' => true]);
    expect($service->generate($schedule))->toBe(1)->and($schedule->fresh()->last_error)->toBeNull();
});

test('automatically paid expenses require opt in and create no bill or duplicate payment', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');
    $service = app(RecurringCostService::class);
    $data = [...recurringPayload($this), 'mode' => 'EXPENSE', 'template' => ['amount' => 3000, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'expense_category_id' => $this->category->id]];
    expect(fn () => $service->save(null, $this->companyId, $this->user->id, $data))->toThrow(ValidationException::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, [...$data, 'auto_record_paid' => true]);
    expect($service->generate($schedule))->toBe(1)->and($service->generate($schedule))->toBe(0)
        ->and((int) Expense::where('supplier_id', $this->supplier->id)->sum('amount'))->toBe(3000)->and(Bill::count())->toBe(0);
});

test('resuming skips paused occurrences and retains the original anchor', function () {
    CarbonImmutable::setTestNow('2026-02-01');
    $service = app(RecurringCostService::class);
    $schedule = $service->save(null, $this->companyId, $this->user->id, recurringPayload($this));
    $service->act($schedule, 'pause', null, $this->user->id);
    CarbonImmutable::setTestNow('2026-03-15');
    $service->act($schedule, 'resume', null, $this->user->id);
    expect($schedule->fresh()->next_run_at)->toBe('2026-03-31')->and($service->generate($schedule))->toBe(0);
});

test('recurring lists filter their mode before pagination and preserve company scope', function () {
    $service = app(RecurringCostService::class);
    $bill = $service->save(null, $this->companyId, $this->user->id, recurringPayload($this));
    $expense = $service->save(null, $this->companyId, $this->user->id, [
        ...recurringPayload($this), 'name' => 'Paid subscription', 'mode' => 'EXPENSE', 'auto_record_paid' => true,
        'template' => ['amount' => 3000, 'currency_id' => $this->currencyId, 'exchange_rate' => 1, 'expense_category_id' => $this->category->id],
    ]);
    $other = $bill->replicate();
    $other->company_id = Company::factory()->create()->id;
    $other->save();

    $this->getJson('/api/v1/recurring-costs?mode=BILL&limit=1')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $bill->id);
    $this->getJson('/api/v1/recurring-costs?mode=EXPENSE&limit=1')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $expense->id);
    $this->getJson('/api/v1/recurring-costs')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/recurring-costs?mode=INVALID')->assertUnprocessable()->assertJsonValidationErrors('mode');
});
