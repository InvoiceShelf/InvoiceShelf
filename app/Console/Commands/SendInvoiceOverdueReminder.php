<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\CompanySetting;
use App\Mail\SendInvoiceOverdueMail;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInvoiceOverdueReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:invoices:overdue {company_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send overdue invoice reminder.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $date = Carbon::now();
        $invoices = Invoice::whereNotIn('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
            ->where('overdue', true)
            ->whereDate('due_date', '<', $date)
            ->get();

        foreach ($invoices as $invoice) {
            $customer = Customer::whereId($invoice['customer_id'])->get()[0];            
            $mail = \Mail::to($customer['email']);

            if ($this->argument('company_id')) {
                $company_bcc = CompanySetting::getSetting('reminders_bcc', (int) $this->argument('company_id'));
                if($company_bcc){
                    $mail->bcc($company_bcc);
                }
            }

            $mail->send(new SendInvoiceOverdueMail($customer, $invoice));
        }
    }
}
