<?php

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
        ->daily();

    Schedule::command('check:estimates:status')
        ->daily();

    $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
    foreach ($recurringInvoices as $recurringInvoice) {
        $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

        Schedule::call(function () use ($recurringInvoice) {
            $recurringInvoice->generateInvoice();
        })->cron($recurringInvoice->frequency)->timezone($timeZone);
    }
}
