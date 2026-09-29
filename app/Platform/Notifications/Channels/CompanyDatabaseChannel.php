<?php

namespace App\Platform\Notifications\Channels;

use App\Platform\Notifications\AppNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The stock database channel, also filling in the company and the record a
 * notice is about, which the stock table has no room for.
 */
class CompanyDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if (! $notification instanceof AppNotification) {
            return $payload;
        }

        $message = $notification->message;

        return array_merge($payload, [
            'company_id' => $message->companyId,
            'subject_type' => $message->subject?->getMorphClass(),
            'subject_id' => $message->subject?->getKey(),
        ]);
    }
}
