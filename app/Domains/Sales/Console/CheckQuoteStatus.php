<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Models\Quote;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep that retires quotes whose offer has run out.
 *
 * Anything that has not already reached a terminal state — accepted, rejected
 * or expired — and whose expiry date fell before today is moved to expired.
 * Drafts are swept along with the rest, and the comparison is on the date
 * alone, so a quote expiring today survives until tomorrow.
 */
class CheckQuoteStatus extends Command
{
    protected $signature = 'check:quotes:status';

    protected $description = 'Check invoices status.';

    /**
     * Expire every quote that has outlived its expiry date.
     */
    public function handle(): void
    {
        $today = Carbon::now();

        $expired = Quote::STATUS_EXPIRED;

        $settled = [
            Quote::STATUS_ACCEPTED,
            Quote::STATUS_REJECTED,
            $expired,
        ];

        $lapsed = Quote::whereNotIn('status', $settled)
            ->whereDate('expiry_date', '<', $today)
            ->get();

        foreach ($lapsed as $quote) {
            $quote->status = $expired;
            printf("Quote %s is EXPIRED \n", $quote->quote_number);
            $quote->save();
        }
    }
}
