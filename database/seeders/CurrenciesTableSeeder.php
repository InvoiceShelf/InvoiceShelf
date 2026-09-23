<?php

namespace Database\Seeders;

use App\Domains\Money\Application\CurrencyCatalog;
use App\Domains\Money\Application\CurrencyService;
use Illuminate\Database\Seeder;

/**
 * The list itself lives in {@see CurrencyCatalog}, so the installer, the admin
 * area's refresh button and this seeder all plant the same rows.
 *
 * Insert-if-absent rather than create: this used to duplicate three rows on
 * every fresh install, because the base-schema consolidation had already
 * inserted them by the time it ran.
 */
class CurrenciesTableSeeder extends Seeder
{
    public function run(CurrencyService $currencies): void
    {
        $currencies->sync();
    }
}
