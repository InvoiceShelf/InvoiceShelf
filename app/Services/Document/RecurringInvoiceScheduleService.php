<?php

namespace App\Services\Document;

use App\Models\CompanySetting;
use Carbon\Carbon;
use Cron\CronExpression;
use DateTimeZone;

class RecurringInvoiceScheduleService
{
    /** @var array<int, string> */
    private array $companyTimezones = [];

    public function companyTimezone(int $companyId): string
    {
        if (isset($this->companyTimezones[$companyId])) {
            return $this->companyTimezones[$companyId];
        }

        $timezone = CompanySetting::getSetting('time_zone', $companyId) ?: config('app.timezone', 'UTC');

        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            $timezone = $this->applicationTimezone();
        }

        return $this->companyTimezones[$companyId] = $timezone;
    }

    public function applicationTimezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    public function firstFutureOccurrence(string $frequency, string $startsAt, int $companyId, ?Carbon $now = null): Carbon
    {
        $timezone = $this->companyTimezone($companyId);
        $start = Carbon::parse($startsAt, $timezone);
        $now = ($now ?: Carbon::now($this->applicationTimezone()))->copy()->setTimezone($timezone);

        if ($start->greaterThan($now)
            && $start->second === 0
            && (new CronExpression($frequency))->isDue($start, $timezone)) {
            return $start;
        }

        return $this->nextOccurrence($frequency, $start->greaterThan($now) ? $start : $now, $timezone);
    }

    public function nextOccurrence(string $frequency, Carbon $after, string $timezone): Carbon
    {
        $cron = new CronExpression($frequency);

        return Carbon::instance($cron->getNextRunDate($after, 0, false, $timezone))
            ->setTimezone($timezone);
    }

    public function fromStored(string $date, int $companyId): Carbon
    {
        return Carbon::parse($date, $this->applicationTimezone())
            ->setTimezone($this->companyTimezone($companyId));
    }

    public function toStored(Carbon $date): string
    {
        return $date->copy()->setTimezone($this->applicationTimezone())->format('Y-m-d H:i:s');
    }
}
