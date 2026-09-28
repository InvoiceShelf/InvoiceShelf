<?php

namespace App\Adapters\Sales;

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Contracts\RecurringInvoiceNotifier;
use App\Domains\Sales\Mail\RecurringInvoiceFailedMail;
use App\Domains\Sales\Mail\RecurringInvoiceGeneratedMail;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the schedule's creator. A schedule whose creator has left the
 * company, or has no address, tells nobody.
 *
 * A notice is a courtesy: a mail server that is down is reported, never
 * allowed to fail the run it is about or stop the runs after it.
 */
class MailRecurringInvoiceNotifier implements RecurringInvoiceNotifier
{
    public function generated(RecurringInvoice $schedule, Invoice $invoice): void
    {
        $this->send($schedule, fn (): Mailable => new RecurringInvoiceGeneratedMail($schedule, $invoice));
    }

    public function failed(RecurringInvoice $schedule, string $reason): void
    {
        $this->send($schedule, fn (): Mailable => new RecurringInvoiceFailedMail($schedule, $reason));
    }

    /**
     * @param  callable(): Mailable  $mail
     */
    private function send(RecurringInvoice $schedule, callable $mail): void
    {
        try {
            $creator = $this->creator($schedule);

            if ($creator !== null) {
                Mail::to($creator->email)->send($mail());
            }
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * The creator, while they still have an address and a seat in the
     * schedule's company.
     */
    private function creator(RecurringInvoice $schedule): ?User
    {
        $creator = $schedule->creator_id ? User::query()->find($schedule->creator_id) : null;

        return $creator?->email && $creator->hasCompany((int) $schedule->company_id) ? $creator : null;
    }
}
