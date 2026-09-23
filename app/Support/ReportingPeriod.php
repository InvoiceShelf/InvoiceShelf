<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The stretch of time a money chart covers, and how it is cut into buckets.
 *
 * Either the company's fiscal year (this one or the one before) or a range
 * the user picked. Up to MAX_DAILY_DAYS days are counted day by day; anything
 * longer goes by calendar month, with the first and last month clipped to the
 * range so a bucket never holds a day outside it.
 */
final class ReportingPeriod
{
    /** The longest range still charted one day at a time. */
    public const MAX_DAILY_DAYS = 62;

    /** The longest range a caller may ask for. */
    public const MAX_YEARS = 5;

    /** @var list<array{start: string, end: string, label: string}>|null */
    private ?array $buckets = null;

    private function __construct(
        private readonly CarbonImmutable $start,
        private readonly CarbonImmutable $end,
    ) {}

    /**
     * Twelve months from the opening month of the company's fiscal year.
     *
     * The opening month is the first dash-separated part of the setting
     * ("4-3" opens in April). A setting that names no month, such as the demo
     * company's "calendar_year", is the calendar year. (The dashboard used to
     * read it as month zero, which rolled the window back to the December
     * before.)
     */
    public static function fiscalYear(?string $fiscalSetting, CarbonInterface $now, bool $previous = false): self
    {
        $openingMonth = intval(explode('-', (string) $fiscalSetting)[0]);

        if ($openingMonth < 1 || $openingMonth > 12) {
            $openingMonth = 1;
        }

        // The first of the month before the month changes, so the last days of
        // a long month cannot spill the window into the one after
        $start = CarbonImmutable::instance($now)->startOfMonth();

        // An opening month still ahead in the calendar year belongs to the
        // fiscal year that opened twelve months ago
        if ($openingMonth > $start->month) {
            $start = $start->subYear();
        }

        $start = $start->month($openingMonth)->startOfMonth();

        if ($previous) {
            $start = $start->subYear();
        }

        return new self($start, $start->addMonths(11)->endOfMonth());
    }

    /** The days from $from to $to, both included. */
    public static function between(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self(
            CarbonImmutable::instance($from)->startOfDay(),
            CarbonImmutable::instance($to)->endOfDay(),
        );
    }

    /**
     * The request's range when it names one, otherwise the fiscal year.
     */
    public static function resolve(
        ?string $fiscalSetting,
        CarbonInterface $now,
        bool $previousYear = false,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): self {
        if ($fromDate !== null && $toDate !== null) {
            return self::between(
                CarbonImmutable::createFromFormat('Y-m-d', $fromDate),
                CarbonImmutable::createFromFormat('Y-m-d', $toDate),
            );
        }

        return self::fiscalYear($fiscalSetting, $now, $previousYear);
    }

    public function from(): string
    {
        return $this->start->toDateString();
    }

    public function to(): string
    {
        return $this->end->toDateString();
    }

    /** @return 'day'|'month' */
    public function granularity(): string
    {
        $days = $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;

        return $days <= self::MAX_DAILY_DAYS ? 'day' : 'month';
    }

    /**
     * The buckets in order: first and last day of each, and its chart label.
     *
     * Days read "3 May". Months read "May" while the whole range fits in twelve
     * of them, and "May 26" beyond that, so no month name appears twice.
     *
     * @return list<array{start: string, end: string, label: string}>
     */
    public function buckets(): array
    {
        if ($this->buckets !== null) {
            return $this->buckets;
        }

        $buckets = [];

        if ($this->granularity() === 'day') {
            for ($day = $this->start->startOfDay(); $day->lte($this->end); $day = $day->addDay()) {
                $buckets[] = [
                    'start' => $day->toDateString(),
                    'end' => $day->toDateString(),
                    'label' => $day->translatedFormat('j M'),
                ];
            }

            return $this->buckets = $buckets;
        }

        $monthCount = $this->monthOffset($this->end) + 1;
        $labelFormat = $monthCount > 12 ? 'M y' : 'M';

        for ($month = $this->start->startOfMonth(); $month->lte($this->end); $month = $month->addMonth()) {
            $buckets[] = [
                'start' => $month->max($this->start)->toDateString(),
                'end' => $month->endOfMonth()->min($this->end)->toDateString(),
                'label' => $month->translatedFormat($labelFormat),
            ];
        }

        return $this->buckets = $buckets;
    }

    /**
     * Which bucket a 'Y-m-d' date falls in, or null when it is outside the range.
     */
    public function bucketIndex(string $date): ?int
    {
        if ($date < $this->from() || $date > $this->to()) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        return $this->granularity() === 'day'
            ? (int) $this->start->startOfDay()->diffInDays($day)
            : $this->monthOffset($day);
    }

    /** @return array{from: string, to: string, granularity: string} */
    public function toArray(): array
    {
        return [
            'from' => $this->from(),
            'to' => $this->to(),
            'granularity' => $this->granularity(),
        ];
    }

    private function monthOffset(CarbonInterface $date): int
    {
        return ($date->year - $this->start->year) * 12 + $date->month - $this->start->month;
    }
}
