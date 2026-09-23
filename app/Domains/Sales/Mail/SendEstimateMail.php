<?php

namespace App\Domains\Sales\Mail;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use App\Support\PublicToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The estimate as its customer receives it.
 *
 * Assembling the message has a side effect. Every send is first written to
 * the email log, and the hashid of that row is the token behind the
 * "view your estimate" link, so the row has to exist — and carry its token —
 * before the body reaches the markdown view.
 *
 * The PDF rides along only when the caller left a renderer in the payload;
 * whether it did is the company's attachment setting talking, decided
 * upstream rather than here.
 */
class SendEstimateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    /**
     * @param  array  $data  sender and recipients, the already-interpolated
     *                       subject and body, the estimate payload, and the
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
        $this->data['url'] = route('estimate', [
            'email_log' => $this->logDelivery(),
        ]);

        $payload = $this->data;

        $message = $this->from($payload['from'], config('mail.from.name'))
            ->subject($payload['subject'])
            ->markdown('emails.send.estimate', [
                // Handed over as a list, not as a keyed array. The numeric
                // keys that produces are inert: the view reads $data, which
                // Laravel already supplies from the public property above.
                'data',
                $this->data,
            ]);

        $pdf = $payload['attach']['data'];

        if ($pdf) {
            $message->attachData(
                $pdf->output(),
                $payload['estimate']['estimate_number'].'.pdf'
            );
        }

        return $message;
    }

    /**
     * Record the outgoing message and give back the token that identifies it
     * in a public link.
     */
    private function logDelivery(): string
    {
        $payload = $this->data;
        $alias = ModelIdentityMap::aliasFor(Estimate::class);

        $log = EmailLog::create([
            'from' => $payload['from'],
            'to' => $payload['to'],
            'cc' => $payload['cc'] ?? null,
            'bcc' => $payload['bcc'] ?? null,
            'subject' => $payload['subject'],
            'body' => $payload['body'],
            'mailable_type' => $alias,
            'mailable_id' => $payload['estimate']['id'],
        ]);

        $log->token = PublicToken::make();

        $log->save();

        return $log->token;
    }
}
