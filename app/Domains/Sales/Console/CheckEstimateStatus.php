<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Operations\Managed\ManagedMode;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep that retires estimates whose offer has run out.
 *
 * Anything that has not already reached a terminal state — accepted, rejected
 * or expired — and whose expiry date fell before today is moved to expired.
 * Drafts are swept along with the rest, and the comparison is on the date
 * alone, so an estimate expiring today survives until tomorrow.
 */
class CheckEstimateStatus extends Command
{
    protected $signature = 'check:estimates:status';

    protected $description = 'Check invoices status.';

    /**
     * Expire every estimate that has outlived its expiry date.
     */
    public function handle(): void
    {
        if (ManagedMode::readOnly()) {
            return;
        }

        $today = Carbon::now();

        $expired = Estimate::STATUS_EXPIRED;

        $settled = [
            Estimate::STATUS_ACCEPTED,
            Estimate::STATUS_REJECTED,
            $expired,
        ];

        $lapsed = Estimate::whereNotIn('status', $settled)
            ->whereDate('expiry_date', '<', $today)
            ->get();

        foreach ($lapsed as $estimate) {
            $estimate->status = $expired;
            printf("Estimate %s is EXPIRED \n", $estimate->estimate_number);
            $estimate->save();
        }
    }
}
