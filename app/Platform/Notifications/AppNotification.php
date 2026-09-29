<?php

namespace App\Platform\Notifications;

use App\Platform\Notifications\Channels\CompanyDatabaseChannel;
use App\Support\SpaTranslations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every notice the app sends staff: a row for the bell, an email, or both,
 * as NotificationCenter decided for the recipient.
 */
class AppNotification extends Notification
{
    /**
     * @param  list<string>  $channels  `database`, `mail`, or both
     * @param  string  $mailLocale  the language the email is written in
     */
    public function __construct(
        public readonly NotificationMessage $message,
        public readonly array $channels,
        public readonly string $mailLocale = 'en',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_map(
            fn (string $channel): string => $channel === 'database' ? CompanyDatabaseChannel::class : $channel,
            $this->channels,
        ));
    }

    /**
     * Stored as the catalogue key rather than the class name, so notices can
     * be told apart by type.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->message->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->message->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->translate($this->message->titleKey());

        return (new MailMessage)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($title)
            ->markdown('emails.notification', [
                'title' => $title,
                'body' => $this->translate($this->message->bodyKey()),
                'url' => $this->message->url ? url($this->message->url) : null,
                'action' => SpaTranslations::get($this->mailLocale, 'inbox.open'),
                'preferences' => $notifiable instanceof AnonymousNotifiable
                    ? null
                    : url('/admin/account-settings/notifications'),
                'preferencesLabel' => SpaTranslations::get($this->mailLocale, 'inbox.mail_footer'),
            ]);
    }

    /**
     * A key filled in with the params, in the email's language.
     */
    private function translate(string $key): string
    {
        $params = [];

        foreach ($this->message->params as $name => $value) {
            $params[$name] = in_array($name, $this->message->translate, true)
                ? SpaTranslations::get($this->mailLocale, $value)
                : $value;
        }

        return SpaTranslations::get($this->mailLocale, $key, $params);
    }
}
