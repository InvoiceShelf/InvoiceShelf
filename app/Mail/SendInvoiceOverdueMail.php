<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Models\Customer;
use App\Models\Company;
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

        // var_dump($mailContent);
        // die;

        if ($this->data['attach']['data']) {
            $mailContent->attachData(
                $this->data['attach']['data']->output(),
                $this->data['invoice']['invoice_number'].'.pdf'
            );
        }

        return $mailContent;
    }

    public function sendInvoiceData()
    {
        $data['invoice'] = $this->invoice->toArray();
        $data['customer'] = $this->customer->toArray();
        $data['company'] = Company::find($this->invoice->company_id);
        $data['subject'] = 'Overdue Invoice'; // $this->getEmailString($data['subject']);
        $data['body'] = "Invoice $this->invoice->invoice_number was due on $this->invoice->due_date. Please pay immediately."; // $this->getEmailString($data['body']);
        $data['attach']['data'] = null; // $this->invoice->getPDFData();

        return $data;
    }
}
