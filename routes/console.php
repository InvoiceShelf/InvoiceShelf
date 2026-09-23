<?php

use App\Platform\Operations\Demo\DemoMode;
use App\Platform\Operations\Installation\Application\InstallationState;
use Illuminate\Support\Facades\Schedule;

// The public demo rebuilds itself on a schedule. It runs even in maintenance
// mode: a reset that failed halfway leaves the site down, and only the next
// reset brings it back.
if (DemoMode::enabled()) {
    Schedule::command('reset:app --force')
        ->cron((string) config('invoiceshelf.demo.reset_cron'))
        ->runInBackground()
        ->withoutOverlapping()
        ->onOneServer()
        ->evenInMaintenanceMode();
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

    // Client registrations that never led to a connection, and OAuth tokens
    // long expired.
    Schedule::command('mcp:prune')
        ->daily();
}
