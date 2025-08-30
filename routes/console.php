<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use App\Space\InstallUtils;
use Illuminate\Support\Facades\Schedule;

// Only run in demo environment
if (config('app.env') === 'demo') {
    Schedule::command('reset:app --force')
        ->daily()
        ->runInBackground()
        ->withoutOverlapping();
}

if (InstallUtils::isDbCreated()) {
    Schedule::command('check:invoices:status')
        ->everyMinute();

    Schedule::command('check:estimates:status')
        ->everyMinute();

    $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
    foreach ($recurringInvoices as $recurringInvoice) {
        $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

        Schedule::call(function () use ($recurringInvoice) {
            $recurringInvoice->generateInvoice();
        })->cron($recurringInvoice->frequency)->timezone($timeZone);
    }

    // -----------------------------------
    //            Reminders
    // -----------------------------------
    $companies = Company::all();
    foreach ($companies as $company) {
        if (CompanySetting::getSetting('reminders_invoice_due', $company->id) === 'YES') {
            $timeZone = CompanySetting::getSetting('time_zone', $company->id);
            $frequency = CompanySetting::getSetting('reminders_invoice_due_frequency', $company->id);

            Schedule::command('send:invoices:overdue '.$company->id)
                ->cron($frequency)->timezone($timeZone);
        }
    }
}
