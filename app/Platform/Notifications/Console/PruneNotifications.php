<?php

namespace App\Platform\Notifications\Console;

use App\Platform\Notifications\Application\Housekeeping;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete read notices older than 90 days and unread ones older than 180';

    public function handle(Housekeeping $housekeeping): int
    {
        $deleted = $housekeeping->prune();

        $this->info("Deleted {$deleted} old notifications.");

        return self::SUCCESS;
    }
}
