<?php

namespace App\Platform\Mail\Application;

use App\Platform\Operations\Managed\ManagedMode;
use Illuminate\Mail\Mailable;

/**
 * Who a company's document mail goes out from.
 *
 * On a managed install, mail that leaves through the provider's transport
 * carries the platform address (MAIL_FROM_ADDRESS, on a domain the provider
 * has authenticated) and the address the user chose becomes Reply-To, so
 * one company can never send as another on the shared sending domain. A
 * company that brings its own SMTP server, and every ordinary install, sends
 * from the address the user chose.
 */
final class OutgoingSender
{
    /**
     * Set the sender on a mailable from the address the user chose.
     */
    public static function apply(Mailable $mail, string $chosenAddress, ?string $name = null): Mailable
    {
        if (! self::throughPlatform()) {
            return $mail->from($chosenAddress, $name);
        }

        return $mail->from((string) config('mail.from.address'), $name)->replyTo($chosenAddress);
    }

    /**
     * Whether this message leaves through the provider's transport rather
     * than a company's own server. applyCompanyConfig() records which.
     */
    public static function throughPlatform(): bool
    {
        return ManagedMode::enabled() && ! config('mail.company_transport', false);
    }
}
