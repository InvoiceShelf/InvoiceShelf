<?php

namespace App\Domains\Sales\Mail;

use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A recurring invoice schedule generated an invoice.
 */
class RecurringInvoiceGeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RecurringInvoice $schedule,
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: __('Recurring invoice for :customer generated', ['customer' => $this->customerName()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recurring-invoice-generated',
            with: [
                'customerName' => $this->customerName(),
                'number' => $this->invoice->invoice_number,
                'date' => $this->invoice->invoice_date,
                'amount' => strip_tags(format_money_pdf($this->invoice->total, $this->invoice->currency)),
                'sent' => $this->schedule->send_automatically,
                'url' => url("/admin/invoices/{$this->invoice->id}/view"),
            ],
        );
    }

    private function customerName(): string
    {
        return (string) ($this->schedule->customer?->name ?? '');
    }
}
