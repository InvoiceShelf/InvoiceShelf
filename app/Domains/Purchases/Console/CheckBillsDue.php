<?php

namespace App\Domains\Purchases\Console;

use App\Domains\Purchases\Events\BillBecameOverdue;
use App\Domains\Purchases\Events\BillDueSoon;
use App\Domains\Purchases\Models\Bill;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep over open bills with something left to pay: those falling due
 * in a few days, and those past their due date. It announces every one it
 * finds on every run; whoever listens keeps a bill to one notice of each kind.
 */
class CheckBillsDue extends Command
{
    /**
     * How many days before its due date a bill is announced.
     */
    public const DAYS_AHEAD = 3;

    protected $signature = 'bills:check-due';

    protected $description = 'Announce open bills that fall due soon or are overdue';

    public function handle(): int
    {
        $today = Carbon::now()->startOfDay();

        $this->open()
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $today->copy()->addDays(self::DAYS_AHEAD))
            ->each(fn (Bill $bill) => BillDueSoon::dispatch((int) $bill->id, (int) $bill->company_id));

        $this->open()
            ->whereDate('due_date', '<', $today)
            ->each(fn (Bill $bill) => BillBecameOverdue::dispatch((int) $bill->id, (int) $bill->company_id));

        return self::SUCCESS;
    }

    private function open()
    {
        return Bill::query()
            ->where('status', 'OPEN')
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_date')
            ->select(['id', 'company_id']);
    }
}
