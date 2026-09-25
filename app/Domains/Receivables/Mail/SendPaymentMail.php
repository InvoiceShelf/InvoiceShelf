<?php

namespace App\Domains\Receivables\Mail;

use App\Domains\Receivables\Models\Payment;
use App\Platform\Mail\Application\OutgoingSender;
use App\Platform\Mail\Contracts\EmailLogWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The receipt as its customer receives it.
 *
 * Putting the message together has a side effect: the send is written to the
 * email log first, because the token that row carries is what the "view your
 * payment" link resolves against, and the link has to be in the body before
 * the body is rendered. The receipt itself is re-read from the database at
 * that moment rather than trusted from the payload handed in, so a receipt
 * that has since gone stops the send instead of logging a link to nothing.
 *
 * The PDF rides along only when the caller left a renderer under
 * `attach.data`. Whether it did is the company's attachment setting talking,
 * decided upstream rather than here.
 */
class SendPaymentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    /**
     * @param  array  $data  sender and recipients, the already-interpolated
     *                       subject and body, the payment payload, and the
     *                       optional PDF renderer under `attach.data`
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
        $this->data['url'] = route('payment', [
            'email_log' => $this->logDelivery(),
        ]);

        $payload = $this->data;

        $message = OutgoingSender::apply($this, $payload['from'], config('mail.from.name'))
            ->subject($payload['subject'])
            ->markdown('emails.send.payment', [
                // Passed as a list, not as a keyed array. The numeric keys
                // that produces are inert: the view reads $data, which
                // Laravel already supplies from the public property above.
                'data',
                $this->data,
            ]);

        $renderer = $payload['attach']['data'];

        if ($renderer) {
            $message->attachData(
                $renderer->output(),
                $payload['payment']['payment_number'].'.pdf'
            );
        }

        return $message;
    }

    /**
     * Write the outgoing message to the email log and give back the token
     * that stands in for it in a public link.
     */
    private function logDelivery(): string
    {
        $payload = $this->data;
        $receipt = Payment::findOrFail($payload['payment']['id']);

        return app(EmailLogWriter::class)->record($receipt, [
            'from' => $payload['from'],
            'to' => $payload['to'],
            'cc' => $payload['cc'] ?? null,
            'bcc' => $payload['bcc'] ?? null,
            'subject' => $payload['subject'],
            'body' => $payload['body'],
        ]);
    }
}
