<?php

namespace App\Adapters\Purchases;

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Contracts\RecurringCostNotifier;
use App\Domains\Purchases\Mail\RecurringCostFailedMail;
use App\Domains\Purchases\Mail\RecurringCostGeneratedMail;
use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Database\Eloquent\Model;
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
class MailRecurringCostNotifier implements RecurringCostNotifier
{
    public function generated(RecurringCost $schedule, Model $record): void
    {
        $this->send($schedule, fn (): Mailable => new RecurringCostGeneratedMail($schedule, $record));
    }

    public function failed(RecurringCost $schedule, string $reason): void
    {
        $this->send($schedule, fn (): Mailable => new RecurringCostFailedMail($schedule, $reason));
    }

    /**
     * @param  callable(): Mailable  $mail
     */
    private function send(RecurringCost $schedule, callable $mail): void
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
     * schedule's company; removing someone from a company does not clear
     * the schedules they set up.
     */
    private function creator(RecurringCost $schedule): ?User
    {
        $creator = $schedule->creator_id ? User::query()->find($schedule->creator_id) : null;

        return $creator?->email && $creator->hasCompany((int) $schedule->company_id) ? $creator : null;
    }
}
