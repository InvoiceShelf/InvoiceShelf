<?php

namespace App\Platform\Storage\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A backup that was asked to report back has been written to its disk.
 */
class BackupCompletedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $option,
        public readonly string $filename,
        public readonly string $diskName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Backup completed: :filename', ['filename' => $this->filename]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.backup-completed',
            with: [
                'option' => $this->option,
                'filename' => $this->filename,
                'diskName' => $this->diskName,
                'url' => url('/admin/administration/settings/backup'),
            ],
        );
    }
}
