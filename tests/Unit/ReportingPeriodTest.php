<?php

use App\Support\ReportingPeriod;
use Carbon\Carbon;

test('a fiscal year opening later in the calendar year is the one that opened last year', function () {
    $period = ReportingPeriod::fiscalYear('7-6', Carbon::parse('2026-03-10'));

    expect($period->from())->toBe('2025-07-01')
        ->and($period->to())->toBe('2026-06-30')
        ->and(array_column($period->buckets(), 'label'))
        ->toBe(['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']);
});

test('a fiscal year that has opened this calendar year starts this year', function () {
    $period = ReportingPeriod::fiscalYear('4-3', Carbon::parse('2026-05-31'));

    expect($period->from())->toBe('2026-04-01')
        ->and($period->to())->toBe('2027-03-31');
});

test('the previous fiscal year is the window a year earlier', function () {
    $period = ReportingPeriod::fiscalYear('1-12', Carbon::parse('2026-06-15'), previous: true);

    expect($period->from())->toBe('2025-01-01')
        ->and($period->to())->toBe('2025-12-31');
});

test('a fiscal setting that names no month is the calendar year', function () {
    $period = ReportingPeriod::fiscalYear('calendar_year', Carbon::parse('2026-06-15'));

    expect($period->from())->toBe('2026-01-01')
        ->and($period->to())->toBe('2026-12-31');
});

test('the last days of a month do not push the window into the next month', function () {
    $period = ReportingPeriod::fiscalYear('2-1', Carbon::parse('2026-03-31'));

    expect($period->from())->toBe('2026-02-01');
});

test('a custom range clips its first and last month', function () {
    $period = ReportingPeriod::between(Carbon::parse('2026-01-15'), Carbon::parse('2026-04-10'));

    expect($period->granularity())->toBe('month')
        ->and($period->buckets())->toBe([
            ['start' => '2026-01-15', 'end' => '2026-01-31', 'label' => 'Jan'],
            ['start' => '2026-02-01', 'end' => '2026-02-28', 'label' => 'Feb'],
            ['start' => '2026-03-01', 'end' => '2026-03-31', 'label' => 'Mar'],
            ['start' => '2026-04-01', 'end' => '2026-04-10', 'label' => 'Apr'],
        ])
        ->and($period->bucketIndex('2026-01-14'))->toBeNull()
        ->and($period->bucketIndex('2026-02-17'))->toBe(1)
        ->and($period->bucketIndex('2026-04-10'))->toBe(3)
        ->and($period->bucketIndex('2026-04-11'))->toBeNull();
});

test('a short range is counted day by day', function () {
    $period = ReportingPeriod::between(Carbon::parse('2026-02-20'), Carbon::parse('2026-03-02'));

    expect($period->granularity())->toBe('day')
        ->and($period->buckets())->toHaveCount(11)
        ->and($period->buckets()[0]['label'])->toBe('20 Feb')
        ->and($period->bucketIndex('2026-03-01'))->toBe(9);
});

test('a range just over two months switches to months', function () {
    $days = ReportingPeriod::between(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-03'));
    $months = ReportingPeriod::between(Carbon::parse('2026-01-01'), Carbon::parse('2026-03-04'));

    expect($days->granularity())->toBe('day')
        ->and($months->granularity())->toBe('month');
});
