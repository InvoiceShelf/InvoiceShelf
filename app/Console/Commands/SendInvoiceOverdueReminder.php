<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderEmailJob;
use App\Models\Invoice;
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
     * The reminders queue name.
     *
     * @var string
     */
    private $queue_name = 'reminders_queue';

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
        $query = Invoice::whereNotIn('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT])
            ->where('overdue', true)
            ->whereDate('due_date', '<', $date);

        if ($this->argument('company_id')) {
            $query = $query->where('company_id', (int) $this->argument('company_id'));
        }

        $invoices = $query->get();
        foreach ($invoices as $invoice) {
            dispatch(new SendReminderEmailJob($invoice));
        }
    }
}
