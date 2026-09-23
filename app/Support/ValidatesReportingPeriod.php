<?php

namespace App\Support;

use Carbon\Carbon;
use DateTime;

/**
 * The period parameters a money chart accepts, for a Form Request.
 *
 * `from_date` and `to_date` ('Y-m-d', both or neither) pick a range of at most
 * ReportingPeriod::MAX_YEARS years. Without them `previous_year`, whatever its
 * value, moves the fiscal-year window back a year.
 */
trait ValidatesReportingPeriod
{
    /**
     * @return array<string, mixed>
     */
    protected function reportingPeriodRules(): array
    {
        $toDate = ['nullable', 'date_format:Y-m-d', 'required_with:from_date', 'after_or_equal:from_date'];

        $fromDate = $this->input('from_date');
        $start = is_string($fromDate) ? DateTime::createFromFormat('!Y-m-d', $fromDate) : false;

        if ($start !== false) {
            $toDate[] = 'before_or_equal:'.Carbon::instance($start)->addYears(ReportingPeriod::MAX_YEARS)->toDateString();
        }

        return [
            'previous_year' => ['sometimes'],
            'from_date' => ['nullable', 'date_format:Y-m-d', 'required_with:to_date'],
            'to_date' => $toDate,
        ];
    }

    /**
     * The period asked for, falling back to the company's fiscal year.
     */
    public function reportingPeriod(?string $fiscalSetting): ReportingPeriod
    {
        return ReportingPeriod::resolve(
            $fiscalSetting,
            Carbon::now(),
            $this->has('previous_year'),
            $this->validated('from_date'),
            $this->validated('to_date'),
        );
    }
}
