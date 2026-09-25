<?php

namespace App\Platform\Operations\Console;

use App\Platform\Operations\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the once-a-day status sweeps (overdue invoices, expired estimates) if
 * they have not run today.
 *
 * The scheduler asks every hour and the Docker entrypoint asks at boot, so an
 * install that was down at midnight catches up at the first chance instead of
 * skipping the day. Both sweeps are idempotent; the recorded date only keeps
 * them to once a day.
 */
class CatchUp extends Command
{
    public const LAST_RUN_SETTING = 'daily_sweeps_ran_on';

    public const SWEEPS = ['check:invoices:status', 'check:estimates:status'];

    protected $signature = 'invoiceshelf:catch-up {--force : Run the sweeps even if they already ran today}';

    protected $description = 'Run the daily status sweeps if they have not run today';

    public function handle(): int
    {
        $today = now()->toDateString();

        if (! $this->option('force') && Setting::getSetting(self::LAST_RUN_SETTING) === $today) {
            $this->components->info('The daily sweeps already ran today.');

            return self::SUCCESS;
        }

        foreach (self::SWEEPS as $sweep) {
            $this->components->task($sweep, fn () => Artisan::call($sweep) === self::SUCCESS);
        }

        Setting::setSetting(self::LAST_RUN_SETTING, $today);

        return self::SUCCESS;
    }
}
