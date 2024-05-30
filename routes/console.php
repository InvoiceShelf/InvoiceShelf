<?php

use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use App\Space\InstallUtils;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

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
