<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\confirm;

class ResetApp extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:app {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean database, database_created and public/storage folder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    /**
     * Execute the console command to reset the application.
     *
     * This will:
     * 1. Enable maintenance mode to prevent access during reset
     * 2. Fresh migrate the database with initial seeds
     * 3. Seed demo data using DemoSeeder
     * 4. Clear all application caches
     * 5. Disable maintenance mode
     *
     * The --force flag can be used to skip confirmation prompt.
     */
    public function handle(): void
    {

        if (! $this->option('force')) {
            if (! confirm('Are you sure you want to reset the application?')) {
                $this->components->error('Reset cancelled');

                return;
            }
        }

        // Enable maintenance mode to prevent access during reset
        $this->info('Activating maintenance mode...');
        Artisan::call('down');

        // Fresh migrate database and run initial seeds
        $this->info('Running migrate:fresh');
        Artisan::call('migrate:fresh --seed --force');

        // Seed demo data
        $this->info('Seeding database');
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

        // Clear all application caches
        $this->info('Clearing cache...');
        Artisan::call('optimize:clear');

        // Disable maintenance mode
        $this->info('Deactivating maintenance mode...');
        Artisan::call('up');

        $this->info('App reset completed successfully!');
    }
}
