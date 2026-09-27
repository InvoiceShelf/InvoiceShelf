<?php

namespace App\Adapters\Sales;

use App\Domains\Sales\Contracts\QuoteEmailSender;
use App\Domains\Sales\Mail\QuoteViewedMail;
use App\Domains\Sales\Mail\SendQuoteMail;
use Illuminate\Support\Facades\Mail;

class LaravelQuoteEmailSender implements QuoteEmailSender
{
    public function send(array $data): void
    {
        $mail = Mail::to($data['to']);

        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }

        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }

        $mail->send(new SendQuoteMail($data));
    }

    public function viewed(array $data, string $recipient): void
    {
        Mail::to($recipient)->send(new QuoteViewedMail($data));
    }
}
