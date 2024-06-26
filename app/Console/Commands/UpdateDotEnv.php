<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        try {
            $this->updateEnvFile([
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

    /**
     * Update the .env file with the provided data.
     *
     * @throws \Exception
     */
    private function updateEnvFile(array $data): void
    {
        $envFile = base_path('.env');

        if (! File::exists($envFile)) {
            throw new \Exception('The .env file does not exist.');
        }

        // Read the contents of the .env file
        $contents = File::get($envFile);

        // Split the contents into an array of lines
        $lines = explode("\n", $contents);

        // Loop through the lines and update the values
        foreach ($lines as &$line) {
            if (empty($line) || Str::startsWith($line, '#')) {
                continue;
            }

            // Split each line into key and value
            $parts = explode('=', $line, 2);
            $key = $parts[0];

            // Check if the key exists in the provided data
            if (isset($data[$key])) {
                // Update the value
                $line = $key.'='.$data[$key];
                unset($data[$key]);
            }
        }

        // Append any new keys that were not present in the original file
        foreach ($data as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        // Combine the lines back into a string
        $updatedContents = implode("\n", $lines);

        // Write the updated contents back to the .env file
        File::put($envFile, $updatedContents);
    }
}
