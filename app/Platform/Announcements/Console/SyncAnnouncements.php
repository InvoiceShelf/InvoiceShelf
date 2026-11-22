<?php

namespace App\Platform\Announcements\Console;

use App\Platform\Announcements\Application\AnnouncementFeed;
use Illuminate\Console\Command;

class SyncAnnouncements extends Command
{
    protected $signature = 'announcements:sync';

    protected $description = 'Copy the InvoiceShelf project announcements from invoiceshelf.com';

    public function handle(AnnouncementFeed $feed): int
    {
        $result = $feed->sync();

        if ($result === null) {
            $this->warn('The announcements feed is switched off or could not be read; nothing changed.');

            return self::SUCCESS;
        }

        $this->info("Announcements synced: {$result['synced']}, removed: {$result['removed']}.");

        return self::SUCCESS;
    }
}
