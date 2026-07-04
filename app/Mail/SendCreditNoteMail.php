<?php

namespace App\Mail;

use App\Facades\Hashids;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for sending a credit note (Stornorechnung) to a customer.
 *
 * A credit note is persisted as an Invoice row (type = CREDIT_NOTE), so the
 * mailable and its EmailLog reference the Invoice model. PR #536 imported a
 * non-existent CreditNote model here (issue #4); this uses the correct import.
 */
class SendCreditNoteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $log = EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
            'cc' => $this->data['cc'] ?? null,
            'bcc' => $this->data['bcc'] ?? null,
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => Invoice::class,
            'mailable_id' => $this->data['invoice']['id'],
        ]);

        $log->token = Hashids::connection(EmailLog::class)->encode($log->id);
        $log->save();

        $this->data['url'] = route('invoice', ['email_log' => $log->token]);

        $mailContent = $this->from($this->data['from'], config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown('emails.send.credit-note', ['data' => $this->data]);

        if ($this->data['attach']['data']) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['invoice']['invoice_number'].'.pdf'
            );
        }

        return $mailContent;
    }
}
