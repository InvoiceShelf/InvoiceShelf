<?php

namespace App\Domains\Money\Console;

use App\Domains\Money\Application\CurrencyCatalog;
use App\Domains\Money\Application\CurrencyService;
use Illuminate\Console\Command;

/**
 * Bring the currencies table in line with the shipped catalogue.
 *
 * The same work the admin area's refresh button does, for the installations
 * that never see it: an upgrade runs this after its migrations, and the
 * container image runs it from its entrypoint. Adding a currency to
 * {@see CurrencyCatalog} is then all it takes
 * for every installation to have it after its next upgrade.
 *
 * Safe to run at any time. Nothing is ever removed, and a code the catalogue
 * does not list is left alone.
 */
class SyncCurrencies extends Command
{
    protected $signature = 'currencies:sync';

    protected $description = 'Add any currency this release ships that the database is missing.';

    public function handle(CurrencyService $currencies): int
    {
        $result = $currencies->sync();

        $added = count($result['added']);
        $updated = count($result['updated']);

        if ($added === 0 && $updated === 0) {
            $this->info('Currencies are already up to date.');

            return self::SUCCESS;
        }

        $this->info("Currencies synced: {$added} added, {$updated} corrected.");

        if ($added > 0) {
            $this->line('  Added: '.implode(', ', $result['added']));
        }

        return self::SUCCESS;
    }
}
