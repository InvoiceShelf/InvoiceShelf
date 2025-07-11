<?php

namespace App\Jobs;

use App\Mail\SendInvoiceOverdueMail;
use App\Models\Customer;
use App\Models\CompanySetting;
use App\Models\EmailLog;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use Throwable;

class SendReminderEmailJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public $invoice;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $customer = Customer::whereId($this->invoice['customer_id'])->get()[0];

            $mail = Mail::to($customer['email']);

            $company_bcc = CompanySetting::getSetting('reminders_bcc', $this->invoice->company->id);
                
            if($company_bcc){
                $mail->bcc($company_bcc);
            }

            $mail_ctx = new SendInvoiceOverdueMail($customer, $this->invoice);
            $mail->send($mail_ctx);

        } catch (Throwable $th) {
            throw new EmailNotSendException(
                $th->getMessage(),
                $mail_ctx->email_log->id
            );
        } 
    }

    /**
     * Handle a queued email's failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Send reminder email failed.');
        Log::error($exception->getMessage());

        EmailLog::destroy($exception->log_id);
    }

    /**
    * The number of seconds after which the job's unique lock will be released.
    *
    * @var int
    */
    public $uniqueFor = 60;
 
    /**
     * Get the unique ID for the job.
    */
    public function uniqueId(): string
    {
        return $this->invoice->id;
    }

}

class EmailNotSendException extends \ErrorException{
    public ?int $log_id = null;
    public ?string $email_driver_msg = null;

    public function __construct(string $email_driver_msg = null, int $log_id, int $severity = E_RECOVERABLE_ERROR)
    {
        $this->message = $email_driver_msg;
        $this->log_id = $log_id;
        $this->severity = $severity;
    }

}
