<?php

namespace App\Platform\Mcp\Tools\Concerns;

use App\Domains\Accounts\Models\CompanySetting;
use App\Support\ReportingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * A named window of time, or a from/to pair, read in the company's time
 * zone. The fiscal years are the dashboard's.
 */
trait ResolvesPeriod
{
    protected const PERIODS = [
        'all_time', 'today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'last_quarter',
        'this_year', 'last_year', 'this_fiscal_year', 'last_fiscal_year',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function periodSchema(JsonSchema $schema, string $default): array
    {
        return [
            'period' => $schema->string()->enum(self::PERIODS)->description("A named window, {$default} unless given. Ignored when from_date and to_date are given."),
            'from_date' => $schema->string()->format('date')->description('Start of a custom window, YYYY-MM-DD, with to_date.'),
            'to_date' => $schema->string()->format('date')->description('End of a custom window, YYYY-MM-DD, included.'),
        ];
    }

    /**
     * The window as two days, both included, and its name; null days for all
     * time.
     *
     * @return array{from: string|null, to: string|null, name: string}
     */
    protected function period(Request $request, int $companyId, string $default): array
    {
        $settings = CompanySetting::getSettings(['time_zone', 'fiscal_year'], $companyId);
        $zone = $settings->get('time_zone') ?: config('app.timezone');
        $now = CarbonImmutable::now($zone);

        $from = $request->get('from_date');
        $to = $request->get('to_date');

        if ($from || $to) {
            $parse = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone)
                : false;

            if (! $parse($from) || ! $parse($to) || $parse($from)->gt($parse($to))) {
                throw ValidationException::withMessages(['from_date' => 'Give both from_date and to_date as YYYY-MM-DD, from before to.']);
            }

            return ['from' => $from, 'to' => $to, 'name' => 'custom'];
        }

        $name = $request->get('period') ?? $default;

        if (! in_array($name, self::PERIODS, true)) {
            throw ValidationException::withMessages(['period' => 'The period is one of: '.implode(', ', self::PERIODS).'.']);
        }

        [$start, $end] = match ($name) {
            'all_time' => [null, null],
            'today' => [$now, $now],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'last_quarter' => [$now->subQuarterNoOverflow()->startOfQuarter(), $now->subQuarterNoOverflow()->endOfQuarter()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'last_year' => [$now->subYearNoOverflow()->startOfYear(), $now->subYearNoOverflow()->endOfYear()],
            'this_fiscal_year', 'last_fiscal_year' => (function () use ($settings, $now, $name) {
                $fiscal = ReportingPeriod::fiscalYear($settings->get('fiscal_year'), $now, $name === 'last_fiscal_year');

                return [CarbonImmutable::parse($fiscal->from()), CarbonImmutable::parse($fiscal->to())];
            })(),
        };

        return ['from' => $start?->toDateString(), 'to' => $end?->toDateString(), 'name' => $name];
    }
}
