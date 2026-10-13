<?php

namespace App\Platform\Storage\Application;

use App\Domains\Accounts\Models\User;
use App\Platform\Storage\Mail\BackupCompletedMail;
use App\Platform\Storage\Mail\BackupFailedMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails whoever started a backup about how it went, when they asked to be
 * told. A user who has since gone, or has no address, is told nothing.
 *
 * An email is a courtesy: a mail server that is down is reported, never
 * allowed to turn a backup that was written into a failed one.
 */
class BackupNotifier
{
    public function completed(?int $userId, string $option, string $filename, string $diskName): void
    {
        $this->send($userId, fn (): Mailable => new BackupCompletedMail($option, $filename, $diskName));
    }

    public function failed(?int $userId, string $option, string $reason): void
    {
        $this->send($userId, fn (): Mailable => new BackupFailedMail($option, $reason));
    }

    /**
     * @param  callable(): Mailable  $mail
     */
    private function send(?int $userId, callable $mail): void
    {
        try {
            $recipient = $userId ? User::query()->find($userId) : null;

            if ($recipient?->email) {
                Mail::to($recipient->email)->send($mail());
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}
