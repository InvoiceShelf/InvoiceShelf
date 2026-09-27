<?php

namespace App\Domains\Sales\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the company that one of its quotes has just been opened.
 *
 * This one travels inward, to the address the company nominated for view
 * notifications, so it goes out under the installation's own mail identity
 * rather than the address the quote itself was sent from.
 */
class QuoteViewedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data;

    /**
     * @param  array  $data  the quote that was opened and the user it
     *                       belongs to, shaped as the view expects them
     */
    public function __construct(
        $data
    ) {
        $this->data = $data;
    }

    /**
     * @return $this
     */
    public function build()
    {
        return $this->subject(__('notification_view_quote'))
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->markdown('emails.viewed.quote', [
                // Handed over as a list, not as a keyed array. The numeric
                // keys that produces are inert: the view reads $data, which
                // Laravel already supplies from the public property above.
                'data',
                $this->data,
            ]);
    }
}
