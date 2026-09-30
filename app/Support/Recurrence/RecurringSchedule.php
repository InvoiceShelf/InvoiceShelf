<?php

namespace App\Support\Recurrence;

/**
 * A schedule the recurrence runner can work: a recurring invoice, or a
 * recurring bill or expense.
 *
 * Every schedule carries the same columns: `company_id`, `frequency` (a cron
 * expression), `starts_at`, `status` (ACTIVE, ON_HOLD or COMPLETED),
 * `limit_by` (NONE, COUNT or DATE) with `limit_count` and `limit_date`, and a
 * next-run column that each schedule names.
 */
interface RecurringSchedule
{
    public const ACTIVE = 'ACTIVE';

    public const ON_HOLD = 'ON_HOLD';

    public const COMPLETED = 'COMPLETED';

    public const LIMIT_NONE = 'NONE';

    public const LIMIT_COUNT = 'COUNT';

    public const LIMIT_DATE = 'DATE';

    /**
     * The column holding the moment the schedule next falls due.
     */
    public function nextRunColumn(): string;

    /**
     * How many records the schedule has generated, for a count limit.
     */
    public function generatedCount(): int;

    /**
     * Whether missed occurrences are each generated (true), or only the
     * latest one before the schedule moves on to the future (false).
     */
    public function catchesUp(): bool;

    /**
     * The time zone the schedule's frequency is read in.
     */
    public function scheduleTimeZone(): string;
}
