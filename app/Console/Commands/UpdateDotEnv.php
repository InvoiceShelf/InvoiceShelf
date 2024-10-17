<?php

namespace App\Console\Commands;

use App\Space\EnvironmentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UpdateDotEnv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:update-dot-env';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the .env file keys for Laravel 11';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environmentManager = new EnvironmentManager();

        try {
            $environmentManager->updateEnv([
                'APP_TIMEZONE' => config('app.timezone') ?? 'UTC',
                'APP_LOCALE' => config('app.locale') ?? 'en',
                'APP_FALLBACK_LOCALE' => config('app.fallback_locale') ?? 'en',
                'APP_FAKER_LOCALE' => config('app.faker_locale') ?? 'en_US',
                'LOG_STACK' => 'single',
                'SESSION_ENCRYPT' => 'false',
                'SESSION_PATH' => '/',
                'SESSION_DOMAIN' => 'null',
                'BROADCAST_CONNECTION' => 'log',
                'FILESYSTEM_DISK' => 'local',
                'QUEUE_CONNECTION' => 'sync',
                'CACHE_STORE' => 'file',
                'CACHE_PREFIX' => '',
                'REDIS_CLIENT' => 'phpredis',
            ]);

            $this->info('The .env file has been updated successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to update the .env file: '.$e->getMessage());
        }

        Artisan::call('config:cache');

        $this->info('Configuration cached successfully.');

    }
}
