<?php

namespace App\Platform\Mcp\Presenters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored date as an ISO day, read from the column itself rather than from
 * the models' formatted accessors.
 */
final class Date
{
    public static function of(Model $model, string $column): ?string
    {
        $value = $model->getRawOriginal($column);

        return $value ? CarbonImmutable::parse($value)->format('Y-m-d') : null;
    }
}
