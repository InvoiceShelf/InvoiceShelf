<?php

use App\Platform\Operations\Installation\Application\InstallationState;
use Illuminate\Support\Facades\Schedule;

// Only run in demo environment
if (config('app.env') === 'demo') {
    Schedule::command('reset:app --force')
        ->daily()
        ->runInBackground()
        ->withoutOverlapping();
}

if (InstallationState::isDbCreated()) {
    Schedule::command('check:invoices:status')
        ->daily();

    Schedule::command('check:estimates:status')
        ->daily();

    // One command that asks which schedules have fallen due, rather than one
    // registered cron entry per recurring invoice. The old shape queried every
    // active schedule on the boot of every artisan command, and only billed
    // when the expression matched the exact minute the scheduler happened to
    // wake up, so a missed minute silently skipped the period.
    Schedule::command('recurring-invoices:generate')
        ->everyMinute()
        ->withoutOverlapping();
}
