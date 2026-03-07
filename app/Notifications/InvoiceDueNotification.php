<?php

namespace App\Notifications;

use App\Mail\SendInvoiceMail;
use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Invoice $invoice;

    /**
     * Create a new notification instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice->loadMissing(['customer', 'company']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable)
    {

        \Log::info('Invoice Due Notification', ['invoice' => $this->invoice]);;

        $companyId = $this->invoice->company_id;

        $subjectTemplate = CompanySetting::getSetting('invoice_due_email_subject', $companyId)
            ?: 'Invoice due reminder: {INVOICE_NUMBER}';
        $bodyTemplate = CompanySetting::getSetting('invoice_due_email_body', $companyId)
            ?: 'This is a friendly reminder that invoice {INVOICE_NUMBER} is due on {INVOICE_DUE_DATE}. Please make the payment at your earliest convenience.';

        $subject = $this->invoice->getEmailString($subjectTemplate);
        $body = $this->invoice->getEmailString($bodyTemplate);

        $attachPdf = CompanySetting::getSetting('invoice_due_attach_pdf', $companyId) === 'YES';
        $bcc = CompanySetting::getSetting('invoice_due_bcc', $companyId);

        $data = [
            'from' => config('mail.from.address'),
            'to' => $this->invoice->customer?->email,
            'subject' => $subject,
            'body' => $body,
            'invoice' => $this->invoice->toArray(),
            'customer' => $this->invoice->customer?->toArray(),
            'company' => $this->invoice->company,
            'attach' => [
                'data' => $attachPdf ? $this->invoice->getPDFData() : null,
            ],
            'bcc' => $bcc,
        ];

        $mailable = new SendInvoiceMail($data);
        // Support BCC if provided
        if (! empty($bcc)) {
            $mailable->bcc($bcc);
        }

        return $mailable;
    }

    /**
     * Get the array representation of the notification for database channel.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'company_id' => $this->invoice->company_id,
            'invoice_number' => $this->invoice->invoice_number,
            'due_date' => $this->invoice->due_date,
            'due_amount' => $this->invoice->due_amount,
            'customer_id' => $this->invoice->customer_id,
            'email' => $this->invoice->customer?->email,
        ];
    }
}
