<?php

namespace App\Domains\Contacts\Notifications;

use App\Support\Urls\CustomerUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The reset-link mail a portal contact receives.
 *
 * It extends the framework notification purely to inherit its token handling.
 * The message is composed here rather than through the framework's callbacks
 * because the link has to carry the company slug the portal hangs off.
 */
class CustomerMailResetPasswordNotification extends ResetPassword
{
    use Queueable;

    /**
     * Hold on to the freshly minted reset token.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct($token)
    {
        parent::__construct($token);
    }

    /**
     * Mail is the only channel this notification travels on.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Compose the reset mail for the contact.
     *
     * The expiry sentence quotes the *user* broker's lifetime even though the
     * token came from the customer broker. Both are configured alike today,
     * so the sentence happens to be accurate.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $slug = $notifiable->company->slug;
        $resetUrl = CustomerUrl::to("/{$slug}/customer/reset/password/".$this->token);
        $minutes = config('auth.passwords.users.expire');

        $opening = 'Hello! You are receiving this email because we received a password reset request for your account.';
        $expiry = 'This password reset link will expire in '.$minutes.' minutes';
        $closing = 'If you did not request a password reset, no further action is required.';

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line($opening)
            ->action('Reset Password', $resetUrl)
            ->line($expiry)
            ->line($closing);
    }

    /**
     * Nothing is stored for the database channel, which is never used.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
