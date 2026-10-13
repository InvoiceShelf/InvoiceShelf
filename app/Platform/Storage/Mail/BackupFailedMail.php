<?php

namespace App\Platform\Storage\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A backup that was asked to report back could not be written.
 */
class BackupFailedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $option,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Backup failed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.backup-failed',
            with: [
                'option' => $this->option,
                'reason' => $this->reason,
                'url' => url('/admin/administration/settings/backup'),
            ],
        );
    }
}
