<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Overdue as the dashboard counts it: an issued invoice with money still
 * owing whose due date is before today in the company's time zone.
 */
final class OverdueInvoices
{
    public static function constrain(Builder $query, int $companyId): Builder
    {
        $zone = CompanySetting::getSetting('time_zone', $companyId) ?: config('app.timezone');

        return $query
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', CarbonImmutable::now($zone)->toDateString());
    }
}
