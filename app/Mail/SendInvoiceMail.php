<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Vinkla\Hashids\Facades\Hashids;

class SendInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data = [];

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $log = EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
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
            ->markdown('emails.send.invoice', ['data', $this->data]);

        if ($this->data['attach']['data']) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['invoice']['invoice_number'].'.pdf'
            );
        }

        return $mailContent;
    }
}
