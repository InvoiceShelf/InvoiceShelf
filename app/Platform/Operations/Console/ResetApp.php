<?php

namespace App\Platform\Operations\Console;

use App\Platform\Operations\Demo\DemoMode;
use App\Platform\Operations\Demo\DemoReset;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\confirm;

/**
 * Throws the instance away and rebuilds it with demo data. Everything in the
 * database goes; there is no undo.
 *
 * On the public demo (APP_ENV=demo) this is the scheduled rebuild, DemoReset:
 * no administrator account, uploaded files removed, the pinned modules
 * installed. Anywhere else it is the development helper below.
 *
 * The app is taken down for the duration so nobody can talk to a half-migrated
 * schema, and brought back up as the final step.
 */
class ResetApp extends Command
{
    use ConfirmableTrait;

    protected $signature = 'reset:app {--force}';

    protected $description = 'Clean database and public/storage folder';

    public function handle(DemoReset $demo): int
    {
        if (! $this->cleared()) {
            $this->components->error('Reset cancelled');

            return self::FAILURE;
        }

        if (DemoMode::enabled()) {
            $demo->run(fn (string $step) => $this->info($step));
            $this->info('The demo was reset.');

            return self::SUCCESS;
        }

        $this->step('Activating maintenance mode...', 'down');
        $this->step('Running migrate:fresh', 'migrate:fresh --seed --force');
        $this->step('Seeding database', 'db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        $this->step('Clearing cache...', 'optimize:clear');
        $this->step('Deactivating maintenance mode...', 'up');

        $this->info('App reset completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Whether the operator has agreed to lose the database — either up front
     * with --force, or by answering the prompt.
     */
    private function cleared(): bool
    {
        return (bool) $this->option('force')
            || confirm('Are you sure you want to reset the application?');
    }

    /**
     * Announce a stage, then hand it to Artisan.
     */
    private function step(string $announcement, string $command, array $arguments = []): void
    {
        $this->info($announcement);

        Artisan::call($command, $arguments);
    }
}
