<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Notifications\InvoiceDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInvoiceDueReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:invoices:due {--company_id=* : Optional company IDs to scope} {--debug : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due invoice reminders to customers based on company notification settings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isVerbose = $this->option('debug');

        if ($isVerbose) {
            $this->info("Starting invoice due reminder process...");
        }

        $companyIds = collect($this->option('company_id'))
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $companies = Company::query()
            ->when(! empty($companyIds), fn ($q) => $q->whereIn('id', $companyIds))
            ->get();

        if ($isVerbose) {
            $this->info("Processing {$companies->count()} companies");
        }

        $totalSent = 0;
        $processedCompanies = 0;

        foreach ($companies as $company) {
            if ($isVerbose) {
                $this->line("Processing company: {$company->name} (ID: {$company->id})");
            }

            $notify = CompanySetting::getSetting('notify_invoice_due', $company->id);

            if ($notify !== 'YES') {
                if ($isVerbose) {
                    $this->line("  Skipped: notifications disabled");
                }
                continue;
            }

            $timeZone = CompanySetting::getSetting('time_zone', $company->id) ?: config('app.timezone', 'UTC');
            $nowTz = Carbon::now($timeZone);

            // Frequency in days (positive integer)
            $frequencyDays = (int) (CompanySetting::getSetting('invoice_due_frequency', $company->id) ?: 1);
            if ($frequencyDays < 1) {
                $frequencyDays = 1;
            }

            // How many days ahead of due date to include (defaults to 0 if missing)
            $dueThresholdDays = (int) (CompanySetting::getSetting('invoice_due_date_days', $company->id) ?: 0);

            // Target date upper bound for upcoming dues
            $dueUpper = (clone $nowTz)->addDays($dueThresholdDays)->endOfDay();

            if ($isVerbose) {
                $this->line("  Frequency: {$frequencyDays} days, Threshold: {$dueThresholdDays} days");
                $this->line("  Checking invoices due by: {$dueUpper->toDateString()}");
            }

            // Fetch invoices unpaid/partially-paid, due by upper bound
            $invoices = Invoice::with(['customer'])
                ->whereCompanyId($company->id)
                ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->whereDate('due_date', '<=', $dueUpper->toDateString())
                ->get();

            if ($isVerbose) {
                $this->line("  Found {$invoices->count()} eligible invoices");
            }

            if ($invoices->isEmpty()) {
                continue;
            }

            $sentCount = 0;

            foreach ($invoices as $invoice) {
                if ($isVerbose) {
                    $this->line("  Processing invoice {$invoice->invoice_number} (ID: {$invoice->id})");
                }

                $customer = $invoice->customer;
                if (! $customer || empty($customer->email)) {
                    if ($isVerbose) {
                        $this->line("    Skipped: no customer email");
                    }
                    continue;
                }

                // Dedupe: check if a due reminder for this invoice was sent within last N days
                $recentSince = (clone $nowTz)->subDays($frequencyDays);

                $existing = $customer->notifications()
                    ->where('type', InvoiceDueNotification::class)
                    ->where('created_at', '>=', $recentSince)
                    ->get()
                    ->first(function ($n) use ($invoice) {
                        $data = (array) $n->data;
                        return isset($data['invoice_id']) && (int) $data['invoice_id'] === (int) $invoice->id;
                    });

                if ($existing) {
                    if ($isVerbose) {
                        $this->line("    Skipped: reminder already sent within {$frequencyDays} days");
                    }
                    continue;
                }

                try {
                    // Only remind if the invoice is currently due or will be due within the threshold
                    $customer->notify(new InvoiceDueNotification($invoice));
                    $this->line("✅ Notification dispatched successfully");

                    // Check if notification was created in database
                    $newNotification = $customer->notifications()
                        ->where('type', InvoiceDueNotification::class)
                        ->latest()
                        ->first();

                    if ($newNotification) {
                        $this->line("✅ Notification saved to database with ID: {$newNotification->id}");
                    } else {
                        $this->error("❌ Notification was not saved to database!");
                    }

                } catch (\Exception $e) {
                    $this->error("❌ Failed to send notification: " . $e->getMessage());
                    $this->error("Stack trace: " . $e->getTraceAsString());
                    continue;
                }

                $sentCount++;
                $this->info("🎉 Reminder sent for invoice {$invoice->invoice_number} (customer {$customer->email})");
            }

            $this->info("📊 Company {$company->id}: {$sentCount} reminder(s) sent.");
        }

        $this->info("✅ InvoiceDueNotification debugging completed!");
        return self::SUCCESS;
    }
}
