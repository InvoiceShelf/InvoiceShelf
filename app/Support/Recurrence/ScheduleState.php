<?php

namespace App\Support\Recurrence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The status rules every recurring schedule follows when someone changes it,
 * shared by recurring invoices and recurring bills and expenses.
 */
final class ScheduleState
{
    /**
     * Carry a schedule on from today: its next run becomes the first one
     * today or later, when the stored one has already gone by. A schedule
     * made active again does not make up the runs it sat out.
     *
     * @param  Model&RecurringSchedule  $schedule
     */
    public static function restartFromToday(Model $schedule): void
    {
        $column = $schedule->nextRunColumn();
        $timezone = $schedule->scheduleTimeZone();
        $startOfToday = CarbonImmutable::now($timezone)->startOfDay();

        if ($schedule->{$column} === null || CarbonImmutable::parse($schedule->{$column})->lessThan($startOfToday)) {
            $schedule->{$column} = Cadence::next($schedule->frequency, $startOfToday->subSecond(), $timezone)->format('Y-m-d H:i:s');
        }
    }

    /**
     * Complete a schedule whose limit leaves no more runs, and make a
     * completed one active again when its limit was extended.
     *
     * @param  Model&RecurringSchedule  $schedule
     */
    public static function settle(Model $schedule): void
    {
        $next = $schedule->{$schedule->nextRunColumn()};

        if ($next === null) {
            return;
        }

        $finished = RecurrenceRunner::limitReached($schedule, Cadence::localDate($next, $schedule->scheduleTimeZone()));

        if ($finished && $schedule->status === RecurringSchedule::ACTIVE) {
            $schedule->status = RecurringSchedule::COMPLETED;
        } elseif (! $finished && $schedule->status === RecurringSchedule::COMPLETED) {
            $schedule->status = RecurringSchedule::ACTIVE;
        }
    }

    /**
     * Apply a save's status rules: settle against the limit, and a schedule
     * made active again, from paused or completed, carries on from today.
     * Call after the new attributes are filled and before saving.
     *
     * @param  Model&RecurringSchedule  $schedule
     * @param  bool  $cadenceChanged  whether the save already moved the next run
     */
    public static function afterEdit(Model $schedule, bool $cadenceChanged): void
    {
        self::settle($schedule);

        if ($schedule->exists && ! $cadenceChanged && $schedule->status === RecurringSchedule::ACTIVE
            && $schedule->getOriginal('status') !== RecurringSchedule::ACTIVE) {
            self::restartFromToday($schedule);
            self::settle($schedule);
        }
    }

    /**
     * The error code a failed run is shown with. The app's own checks fail
     * with a code; a form rule fails with a sentence about a field, which a
     * schedule's page and email cannot place, so it is shown as the given
     * generic code, asking for the schedule to be opened and saved again.
     */
    public static function failureReason(Throwable $error, string $unreadable, string $unexpected): string
    {
        if (! $error instanceof ValidationException) {
            return $unexpected;
        }

        $message = (string) (Arr::flatten($error->errors())[0] ?? '');

        return preg_match('/^[a-z][a-z0-9_]*$/', $message) === 1 ? $message : $unreadable;
    }
}
