<?php

namespace App\Platform\Mcp\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows a query to the days of a window, both included, on a date column
 * that may also carry a time.
 */
final class Window
{
    public static function apply(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            $query->where($column, '<', CarbonImmutable::parse($to)->addDay()->toDateString());
        }

        return $query;
    }
}
