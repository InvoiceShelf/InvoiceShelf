<?php

namespace App\Domains\Sales\Mail;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Support\SpaTranslations;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A recurring invoice could not be generated or emailed. Sent when the
 * failures begin, and again only when the reason changes.
 */
class RecurringInvoiceFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RecurringInvoice $schedule,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Recurring invoice for :customer needs attention', ['customer' => $this->customerName()]),
        );
    }

    public function content(): Content
    {
        $locale = CompanySetting::getSetting('language', $this->schedule->company_id) ?: 'en';

        return new Content(
            markdown: 'emails.recurring-invoice-failed',
            with: [
                'customerName' => $this->customerName(),
                'reason' => SpaTranslations::get($locale, 'errors.'.$this->reason),
                'sendFailed' => $this->reason === 'recurring_invoice_send_failed',
                'url' => url("/admin/recurring-invoices/{$this->schedule->id}/view"),
            ],
        );
    }

    private function customerName(): string
    {
        return (string) ($this->schedule->customer?->name ?? '');
    }
}
