<?php

namespace App\Domains\Purchases\Console;

use App\Domains\Purchases\Events\BillBecameOverdue;
use App\Domains\Purchases\Events\BillDueSoon;
use App\Domains\Purchases\Models\Bill;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep over open bills with something left to pay: those falling due
 * in a few days, and those that went past their due date lately. It announces
 * every one it finds on every run; whoever listens keeps a bill to one notice
 * of each kind. Bills overdue for longer are left alone, so an old bill is
 * never announced again once its notice has been pruned.
 */
class CheckBillsDue extends Command
{
    /**
     * How many days before its due date a bill is announced.
     */
    public const DAYS_AHEAD = 3;

    /**
     * How many days after its due date a bill can still be announced as
     * overdue, so an install that was down for a while catches up.
     */
    public const OVERDUE_DAYS = 14;

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
            ->whereDate('due_date', '>=', $today->copy()->subDays(self::OVERDUE_DAYS))
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
