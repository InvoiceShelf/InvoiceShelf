<?php

namespace App\Adapters\Purchases;

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Contracts\RecurringCostNotifier;
use App\Domains\Purchases\Mail\RecurringCostFailedMail;
use App\Domains\Purchases\Mail\RecurringCostGeneratedMail;
use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the schedule's creator. A schedule whose creator has left the
 * company, or has no address, tells nobody.
 */
class MailRecurringCostNotifier implements RecurringCostNotifier
{
    public function generated(RecurringCost $schedule, Model $record): void
    {
        $creator = $this->creator($schedule);

        if ($creator !== null) {
            Mail::to($creator->email)->send(new RecurringCostGeneratedMail($schedule, $record));
        }
    }

    public function failed(RecurringCost $schedule, string $reason): void
    {
        $creator = $this->creator($schedule);

        if ($creator !== null) {
            Mail::to($creator->email)->send(new RecurringCostFailedMail($schedule, $reason));
        }
    }

    private function creator(RecurringCost $schedule): ?User
    {
        $creator = $schedule->creator_id ? User::query()->find($schedule->creator_id) : null;

        return $creator?->email ? $creator : null;
    }
}
