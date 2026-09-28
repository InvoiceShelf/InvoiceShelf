<?php

namespace App\Support\Recurrence;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;

/**
 * When a recurring schedule next falls due.
 *
 * A frequency is a cron expression, read in the company's time zone, so a
 * monthly schedule runs at the start of the company's month and not the
 * server's. Moments go in and come out in the application's zone, which is
 * how the next-run columns are stored and compared with `now()`.
 */
final class Cadence
{
    public static function isValid(?string $frequency): bool
    {
        return is_string($frequency) && CronExpression::isValidExpression($frequency);
    }

    /**
     * The first occurrence strictly after the given moment.
     */
    public static function next(string $frequency, CarbonInterface|string $after, string $timezone): CarbonImmutable
    {
        $appZone = config('app.timezone', 'UTC');
        $from = CarbonImmutable::parse($after, $appZone)->setTimezone($timezone);

        $next = (new CronExpression($frequency))->getNextRunDate($from, 0, false, $timezone);

        return CarbonImmutable::instance($next)->setTimezone($appZone);
    }

    /**
     * The next occurrences after the given moment, for a preview.
     *
     * @return list<CarbonImmutable>
     */
    public static function upcoming(string $frequency, CarbonInterface|string $after, string $timezone, int $count = 5): array
    {
        $dates = [];
        $moment = $after;

        for ($i = 0; $i < $count; $i++) {
            $moment = self::next($frequency, $moment, $timezone);
            $dates[] = $moment;
        }

        return $dates;
    }

    /**
     * The calendar date of a moment where the company is: the date a document
     * generated for that occurrence carries.
     */
    public static function localDate(CarbonInterface|string $moment, string $timezone): string
    {
        return CarbonImmutable::parse($moment, config('app.timezone', 'UTC'))->setTimezone($timezone)->toDateString();
    }
}
