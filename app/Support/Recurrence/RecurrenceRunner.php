<?php

namespace App\Support\Recurrence;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Generates what recurring schedules have fallen due for.
 *
 * Each occurrence is one transaction: the schedule row is locked, checked to
 * be still active and due, checked against its limit, the record is generated
 * for the occurrence's own date, and the next run is moved on. Two runners
 * working the same schedule therefore generate it once, and an occurrence
 * whose generation fails is neither lost nor half written: it stays due and
 * is tried again, at most once an hour so a broken template does not fill the
 * log every minute.
 *
 * A schedule that catches up generates every occurrence it missed (up to
 * CATCH_UP_LIMIT a run); one that does not generates a single record, dated
 * the latest occurrence it missed, and moves on to the first one in the future.
 */
final class RecurrenceRunner
{
    /** The most occurrences one schedule generates in a single run. */
    public const CATCH_UP_LIMIT = 100;

    /** How long a failed schedule is left alone before it is tried again. */
    public const RETRY_AFTER_SECONDS = 3600;

    /**
     * Work every active schedule in the query that has fallen due.
     *
     * @param  Builder<Model&RecurringSchedule>  $schedules
     * @param  Closure(Model&RecurringSchedule, string, CarbonImmutable): void  $generate  given the schedule, the occurrence's date where the company is, and its moment
     * @param  (Closure(Model&RecurringSchedule, Throwable): void)|null  $failed  told about a failed occurrence after its transaction rolled back
     * @return int the occurrences generated
     */
    public function run(Builder $schedules, Closure $generate, ?Closure $failed = null): int
    {
        $column = $schedules->getModel()->nextRunColumn();
        $generated = 0;

        $schedules
            ->where('status', RecurringSchedule::ACTIVE)
            ->whereNotNull($column)
            ->where($column, '<=', $this->now()->format('Y-m-d H:i:s'))
            ->orderBy('id')
            ->chunkById(100, function ($batch) use ($generate, $failed, &$generated): void {
                foreach ($batch as $schedule) {
                    if (Cache::has($this->failureKey($schedule))) {
                        continue;
                    }

                    $generated += $this->work($schedule, $generate, $failed);
                }
            });

        return $generated;
    }

    /**
     * Generate what one schedule is owed now.
     *
     * @param  Model&RecurringSchedule  $schedule
     * @param  Closure(Model&RecurringSchedule, string, CarbonImmutable): void  $generate
     * @param  (Closure(Model&RecurringSchedule, Throwable): void)|null  $failed
     * @return int the occurrences generated
     */
    public function work(Model $schedule, Closure $generate, ?Closure $failed = null): int
    {
        $generated = 0;

        for ($i = 0; $i < self::CATCH_UP_LIMIT; $i++) {
            try {
                [$made, $more] = DB::transaction(fn (): array => $this->step($schedule, $generate));
            } catch (Throwable $error) {
                Cache::put($this->failureKey($schedule), time(), self::RETRY_AFTER_SECONDS);

                if ($failed) {
                    $failed($schedule->fresh() ?? $schedule, $error);
                } else {
                    report($error);
                }

                return $generated;
            }

            $generated += $made;

            if (! $more) {
                break;
            }
        }

        if ($generated > 0) {
            Cache::forget($this->failureKey($schedule));
        }

        return $generated;
    }

    /**
     * Whether a schedule has reached its limit for an occurrence on the given
     * date: its count is used up, or the date is past its end date.
     */
    public static function limitReached(Model $schedule, string $date): bool
    {
        return match ($schedule->limit_by) {
            RecurringSchedule::LIMIT_COUNT => $schedule->generatedCount() >= (int) $schedule->limit_count,
            RecurringSchedule::LIMIT_DATE => $schedule->limit_date !== null && $date > substr((string) $schedule->limit_date, 0, 10),
            default => false,
        };
    }

    /**
     * Generate one occurrence inside the caller's transaction.
     *
     * @return array{0: int, 1: bool} generated (0 or 1), and whether another occurrence is already due
     */
    private function step(Model $schedule, Closure $generate): array
    {
        /** @var (Model&RecurringSchedule)|null $row */
        $row = $schedule::query()->whereKey($schedule->getKey())->lockForUpdate()->first();
        $column = $schedule->nextRunColumn();

        if ($row === null || $row->status !== RecurringSchedule::ACTIVE || $row->{$column} === null) {
            return [0, false];
        }

        $at = CarbonImmutable::parse($row->{$column}, config('app.timezone', 'UTC'));

        if ($at->greaterThan($this->now())) {
            return [0, false];
        }

        $timezone = $row->scheduleTimeZone();

        if (! $row->catchesUp()) {
            $at = $this->latestDue($row->frequency, $at, $timezone);
        }

        $date = Cadence::localDate($at, $timezone);

        if (self::limitReached($row, $date)) {
            $row->status = RecurringSchedule::COMPLETED;
            $row->save();

            return [0, false];
        }

        $generate($row, $date, $at);

        $next = Cadence::next($row->frequency, $at, $timezone);
        $row->{$column} = $next->format('Y-m-d H:i:s');

        if (self::limitReached($row, Cadence::localDate($next, $timezone))) {
            $row->status = RecurringSchedule::COMPLETED;
        }

        $row->save();

        $more = $row->status === RecurringSchedule::ACTIVE
            && $row->catchesUp()
            && ! $next->greaterThan($this->now());

        return [1, $more];
    }

    /**
     * The last occurrence that is not in the future, starting from one that
     * has fallen due.
     */
    private function latestDue(string $frequency, CarbonImmutable $due, string $timezone): CarbonImmutable
    {
        $now = $this->now();

        for ($i = 0; $i < 10000; $i++) {
            $next = Cadence::next($frequency, $due, $timezone);

            if ($next->greaterThan($now)) {
                return $due;
            }

            $due = $next;
        }

        return $due;
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone', 'UTC'));
    }

    private function failureKey(Model $schedule): string
    {
        return 'recurrence:failed:'.$schedule->getTable().':'.$schedule->getKey();
    }
}
