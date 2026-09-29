<?php

namespace App\Domains\Sales\Console;

use App\Domains\Sales\Application\InvoiceReminderService;
use Illuminate\Console\Command;

/**
 * Hourly: email the payment reminders that have come due, in each company
 * once its local time reaches the hour it chose.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Email customers the payment reminders that are due';

    public function handle(InvoiceReminderService $reminders): int
    {
        $sent = $reminders->sendDue();

        $this->info("Sent {$sent} payment reminders.");

        return self::SUCCESS;
    }
}
