<?php

use App\Support\Recurrence\Cadence;
use Carbon\CarbonImmutable;

/**
 * Cron frequencies read in the company's time zone, including the days the
 * calendar or the clocks make awkward. Moments come back in the app zone
 * (UTC here).
 */
beforeEach(fn () => config(['app.timezone' => 'UTC']));

/**
 * @return list<string>
 */
function upcomingLocal(string $frequency, string $after, string $timezone, int $count, string $format = 'Y-m-d'): array
{
    return array_map(
        fn (CarbonImmutable $moment) => $moment->setTimezone($timezone)->format($format),
        Cadence::upcoming($frequency, $after, $timezone, $count),
    );
}

test('the next run is strictly after the moment given', function () {
    expect(Cadence::next('0 0 * * *', '2026-06-15 00:00:00', 'UTC')->format('Y-m-d H:i'))->toBe('2026-06-16 00:00')
        ->and(Cadence::next('0 0 * * *', '2026-06-14 23:59:59', 'UTC')->format('Y-m-d H:i'))->toBe('2026-06-15 00:00');
});

test('a bare date is that day where the company is, a moment is in the app zone', function () {
    // Midnight on 1 October in New York is 04:00 UTC.
    expect(Cadence::next('0 0 * * *', '2026-10-01', 'America/New_York')->format('Y-m-d H:i'))->toBe('2026-10-02 04:00')
        ->and(Cadence::next('0 0 * * *', '2026-10-01 03:59:59', 'America/New_York')->format('Y-m-d H:i'))->toBe('2026-10-01 04:00');
});

test('the 31st skips the months without one, and L is the last day of every month', function () {
    expect(upcomingLocal('0 0 31 * *', '2026-01-30', 'UTC', 3))->toBe(['2026-01-31', '2026-03-31', '2026-05-31'])
        ->and(upcomingLocal('0 0 L * *', '2026-01-30', 'UTC', 3))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

test('a time lost when the clocks go forward runs once, an hour later', function () {
    // 29 March 2026: 02:00 jumps to 03:00 in Skopje.
    expect(upcomingLocal('30 2 * * *', '2026-03-27', 'Europe/Skopje', 4, 'Y-m-d H:i'))
        ->toBe(['2026-03-27 02:30', '2026-03-28 02:30', '2026-03-29 03:30', '2026-03-30 02:30']);
});

test('a time repeated when the clocks go back runs once', function () {
    // 25 October 2026: 03:00 falls back to 02:00 in Skopje, so 02:30 happens twice.
    expect(upcomingLocal('30 2 * * *', '2026-10-23', 'Europe/Skopje', 4))
        ->toBe(['2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26']);
});

test('an hourly schedule runs once at each wall-clock hour through the repeated hour', function () {
    expect(upcomingLocal('0 * * * *', '2026-10-24 23:30:00', 'Europe/Skopje', 4, 'H:i T'))
        ->toBe(['02:00 CEST', '03:00 CET', '04:00 CET', '05:00 CET']);
});

test('the latest run at or before a moment includes an exact match', function () {
    // Midnight on 1 June in Skopje is 22:00 UTC on 31 May.
    expect(Cadence::latestAtOrBefore('0 0 1 * *', '2026-05-31 22:00:00', 'Europe/Skopje')->format('Y-m-d H:i'))->toBe('2026-05-31 22:00')
        ->and(Cadence::latestAtOrBefore('0 0 1 * *', '2026-06-10 10:00:00', 'Europe/Skopje')->format('Y-m-d H:i'))->toBe('2026-05-31 22:00');
});

test('a run is dated the day it falls on where the company is', function () {
    expect(Cadence::localDate('2026-06-14 22:30:00', 'Europe/Skopje'))->toBe('2026-06-15')
        ->and(Cadence::localDate('2026-06-15 03:00:00', 'America/New_York'))->toBe('2026-06-14');
});

test('the preview lists the requested number of runs in order', function () {
    $runs = Cadence::upcoming('0 0 * * 1', '2026-06-15', 'UTC', 5);

    expect($runs)->toHaveCount(5)
        ->and(collect($runs)->map->format('Y-m-d')->all())->toBe(['2026-06-22', '2026-06-29', '2026-07-06', '2026-07-13', '2026-07-20']);
});
