<?php

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

    // One command that bills whatever has fallen due, rather than one cron
    // entry per schedule: those only billed in the exact minute their
    // expression matched, so a missed minute skipped the period.
    Schedule::command('recurring-invoices:generate')
        ->everyMinute()
        ->withoutOverlapping();
}
