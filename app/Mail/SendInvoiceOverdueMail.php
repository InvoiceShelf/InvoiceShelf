<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Events\JobProcessed;
use Vinkla\Hashids\Facades\Hashids;

use Throwable;
use Queue;

class SendInvoiceOverdueMail extends Mailable
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public Customer $customer;
    public Invoice $invoice;

    public $data = [];
    public $email_log;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        Customer $customer,
        Invoice $invoice
    ) {
        $this->customer = $customer;
        $this->invoice = $invoice;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        // @todo this uses the general mail config
        // There should be a company setting for this
        //
        $from_address = config('mail.from.address');
        $from_name = config('mail.from.name');

        $this->data = $this->sendInvoiceData();

        $this->email_log = EmailLog::create([
            'from' =>  "$from_address",
            'to' => $this->to[0]['address'],
            'subject' => $this->data['subject'],
            'body' => $this->data['body'],
            'mailable_type' => Invoice::class,
            'mailable_id' => $this->data['invoice']['id'],
        ]);

        $this->email_log->token = Hashids::connection(EmailLog::class)->encode($this->email_log->id);
        $this->email_log->save();

        $this->data['url'] = route('invoice', ['email_log' => $this->email_log->token]);   
       
        $mailContent = $this->from($from_address, $from_name)
            ->subject($this->data['subject'])
            ->markdown('emails.send.invoice_overdue', ['data', $this->data]);

        if ($this->getEmailAttachmentSetting()) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['invoice']['invoice_number'].'.pdf'
            );
        };

        return $mailContent;
    }

    public function getEmailAttachmentSetting() {
        return CompanySetting::getSetting('reminders_attach_pdf', $this->invoice->company_id) === '1';
    }
  
    public function sendInvoiceData()
    {
        $email_subject = CompanySetting::getSetting('reminders_invoice_due_email_subject', $this->invoice->company_id);
        $email_body = CompanySetting::getSetting('reminders_invoice_due_email_body', $this->invoice->company_id);

        $data['invoice'] = $this->invoice->toArray();
        $data['customer'] = $this->customer->toArray();
        $data['company'] = Company::find($this->invoice->company_id);
        $data['subject'] = $this->invoice->getEmailString($email_subject);
        $data['body'] = $this->invoice->getEmailString($email_body);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->invoice->getPDFData() : null;
       
        return $data;
    }

}

