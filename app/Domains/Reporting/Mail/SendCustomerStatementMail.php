<?php

namespace App\Domains\Reporting\Mail;

use App\Platform\Mail\Application\OutgoingSender;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendCustomerStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        EmailLog::create([
            'from' => $this->data['from'],
            'to' => $this->data['to'],
            'cc' => $this->data['cc'] ?? null,
            'bcc' => $this->data['bcc'] ?? null,
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => $this->data['customer']->getMorphClass(),
            'mailable_id' => $this->data['customer']->id,
        ]);

        return OutgoingSender::apply($this, $this->data['from'], $this->data['from_name'])
            ->subject($this->data['subject'])
            ->markdown('emails.send.customer-statement', ['data' => $this->data])
            ->attachData($this->data['pdf']->output(), $this->data['filename']);
    }
}
