<?php

namespace App\Domains\Money\Models;

use App\Domains\Money\Application\CurrencyCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global currency reference data.
 *
 * Rows are seeded at install time and are shared by every company — there is
 * no CRUD surface for them. An `exchange_rate` is not stored here; callers
 * that resolve one hang it on the instance before it is serialised.
 *
 * {@see CurrencyCatalog} is where the rows come
 * from, and `CurrencyService::sync()` is the only thing that writes them.
 */
class Currency extends Model
{
    use HasFactory;

    /**
     * Codes pinned to the front of the currency listing, in this order.
     */
    public const COMMON_CURRENCY_CODES = [
        'USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR', 'BRL',
    ];

    protected $table = 'currencies';

    protected $guarded = [
        'id',
    ];

    /**
     * Without these the columns come back as driver-shaped scalars, and a sync
     * comparing `false` against `0` would rewrite every row on every run.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precision' => 'integer',
            'swap_currency_symbol' => 'boolean',
        ];
    }
}
