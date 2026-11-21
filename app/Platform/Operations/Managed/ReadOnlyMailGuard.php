<?php

namespace App\Platform\Operations\Managed;

use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Domains\Contacts\Notifications\CustomerMailResetPasswordNotification;
use Illuminate\Mail\Events\MessageSending;

class ReadOnlyMailGuard
{
    public function handle(MessageSending $event): ?bool
    {
        if (! ManagedMode::readOnly()) {
            return null;
        }

        // Laravel supplies this metadata from the notification class, not from a template or input.
        if (in_array($event->data['__laravel_notification'] ?? null, [MailResetPasswordNotification::class, CustomerMailResetPasswordNotification::class], true)) {
            return null;
        }

        return false;
    }
}
