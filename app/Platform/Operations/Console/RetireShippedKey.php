<?php

namespace App\Platform\Operations\Console;

use App\Platform\Operations\Application\ShippedKeyRetirement;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Takes an installation off the APP_KEY that `.env.example` used to ship; see
 * ShippedKeyRetirement. The Docker entrypoint runs it on every start and the
 * updater at the end of every update, so it is cheap when there is nothing
 * to do.
 */
class RetireShippedKey extends Command
{
    protected $signature = 'invoiceshelf:retire-shipped-key
        {--rotate : Replace the key in .env first when it is the shipped one}';

    protected $description = 'Move off the public APP_KEY that .env.example used to ship';

    public function handle(ShippedKeyRetirement $retirement): int
    {
        $key = (string) config('app.key');

        if ($retirement->isShipped($key)) {
            if (! $this->option('rotate')) {
                $this->components->error('APP_KEY is the key InvoiceShelf used to ship, which is public. Run this again with --rotate, or set a key of your own with php artisan key:generate.');

                return self::FAILURE;
            }

            try {
                $key = $retirement->rotate();
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            $this->components->warn('APP_KEY replaced: it was the key InvoiceShelf used to ship. Everyone has to sign in again.');
        }

        if (($resealed = $retirement->resealCredentials($key)) > 0) {
            $this->components->info("Marketplace credential sealed with the current key ({$resealed}).");
        }

        return self::SUCCESS;
    }
}
