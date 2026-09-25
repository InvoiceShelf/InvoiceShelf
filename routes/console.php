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

// Nothing below runs before the installer has built the schema; the check
// happens when the scheduler runs, not when this file loads.
$installed = fn (): bool => (bool) InstallationState::isDbCreated();

// The daily status sweeps (overdue invoices, expired estimates). Asked for
// every hour and kept to once a day by invoiceshelf:catch-up, so an install
// that was down at midnight catches up instead of skipping the day.
Schedule::command('invoiceshelf:catch-up')
    ->hourly()
    ->withoutOverlapping()
    ->when($installed);

// One command that asks which schedules have fallen due, rather than one
// registered cron entry per recurring invoice. The old shape queried every
// active schedule on the boot of every artisan command, and only billed
// when the expression matched the exact minute the scheduler happened to
// wake up, so a missed minute silently skipped the period.
Schedule::command('recurring-invoices:generate')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($installed);

// Client registrations that never led to a connection, and OAuth tokens
// long expired.
Schedule::command('mcp:prune')
    ->daily()
    ->when($installed);
