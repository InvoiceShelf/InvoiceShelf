<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Models\Customer;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Vinkla\Hashids\Facades\Hashids;

class SendInvoiceOverdueMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public Customer $customer;
    public Invoice $invoice;

    public $data = [];

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
               
        // $log = EmailLog::create([
        //     'from' => $this->data['from'],
        //     'to' => $this->data['to'],
        //     'subject' => $this->data['subject'],
        //     'body' => $this->data['body'],
        //     'mailable_type' => Invoice::class,
        //     'mailable_id' => $this->data['invoice']['id'],
        // ]);

        // $log->token = Hashids::connection(EmailLog::class)->encode($log->id);
        // $log->save();

        $this->data = $this->sendInvoiceData();

        // $this->data['url'] = route('invoice', ['email_log' => $log->token]);        
        $this->data['url'] = 'FAKE_URL';        

       
        $mailContent = $this->from('test@turtlehaus.com', config('mail.from.name'))
            ->subject($this->data['subject'])
            ->markdown('emails.send.invoice_overdue', ['data', $this->data]);

        if ($this->getEmailAttachmentSetting()) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['invoice']['invoice_number'].'.pdf'
            );
        }

        // var_dump($this->getEmailAttachmentSetting());
        // die;

        return $mailContent;
    }

    public function getEmailAttachmentSetting() {
        return CompanySetting::getSetting('reminders_attach_pdf', $this->invoice->company_id) === '1';
    }

    public function sendInvoiceData()
    {
        // $data['invoice'] = $this->toArray();
        // $data['customer'] = $this->customer->toArray();
        // $data['company'] = Company::find($this->company_id);
        // $data['subject'] = $this->getEmailString($data['subject']);
        // $data['body'] = $this->getEmailString($data['body']);
        // $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->getPDFData() : null;

        $email_body = CompanySetting::getSetting('reminders_invoice_due_email_body', $this->invoice->company_id);

        $data['invoice'] = $this->invoice->toArray();
        $data['customer'] = $this->customer->toArray();
        $data['company'] = Company::find($this->invoice->company_id);
        $data['subject'] = 'Overdue Invoice'; // $this->getEmailString($data['subject']);
        $data['body'] = $this->invoice->getEmailString($email_body);
        $data['attach']['data'] = ($this->getEmailAttachmentSetting()) ? $this->invoice->getPDFData() : null;
       

        return $data;
    }
}
