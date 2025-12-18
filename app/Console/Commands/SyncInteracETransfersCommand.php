<?php

namespace App\Console\Commands;

use App\Services\InteracETransferService;
use Illuminate\Console\Command;

class SyncInteracETransfersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interac:sync {--company= : Limit sync to a single company id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Interac e-Transfer inboxes and create payments automatically';

    /**
     * Execute the console command.
     */
    public function handle(InteracETransferService $service): int
    {
        $companyId = $this->option('company');
        $results = $service->sync($companyId ? (int) $companyId : null);

        if (! $results) {
            $this->warn('No companies were processed. Is the IMAP extension enabled?');

            return self::SUCCESS;
        }

        foreach ($results as $companyId => $result) {
            $this->line(sprintf(
                'Company %s -> status: %s, checked: %s, payments created: %s',
                $companyId,
                $result['status'] ?? 'unknown',
                $result['messages_checked'] ?? 0,
                $result['payments_created'] ?? 0
            ));
        }

        return self::SUCCESS;
    }
}
