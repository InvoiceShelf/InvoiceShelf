<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CreateDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:create {--fresh : Run fresh migrations before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create comprehensive demo data for InvoiceShelf demonstration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Creating InvoiceShelf Demo Data...');
        $this->newLine();

        // Check if fresh migrations should be run
        if ($this->option('fresh')) {
            $this->warn('⚠️  This will delete all existing data!');

            if (! $this->confirm('Are you sure you want to run fresh migrations?')) {
                $this->info('Operation cancelled.');

                return 0;
            }

            $this->info('Running fresh migrations...');
            Artisan::call('migrate:fresh');
            $this->info('✅ Fresh migrations completed.');
            $this->newLine();
        }

        // Check if database has basic data (currencies, countries)
        $this->info('Checking database requirements...');

        try {
            $currencyCount = DB::table('currencies')->count();
            $countryCount = DB::table('countries')->count();

            if ($currencyCount === 0 || $countryCount === 0) {
                $this->info('Installing basic data (currencies and countries)...');
                Artisan::call('db:seed', ['--class' => 'CurrenciesTableSeeder']);
                Artisan::call('db:seed', ['--class' => 'CountriesTableSeeder']);
                $this->info('✅ Basic data installed.');
            } else {
                $this->info('✅ Basic data already exists.');
            }
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed: '.$e->getMessage());
            $this->info('Please ensure your database is properly configured and accessible.');

            return 1;
        }

        $this->newLine();

        // Run the demo data seeder
        $this->info('Creating demo data...');

        try {
            $seeder = new DemoDataSeeder;
            $seeder->setCommand($this);
            $seeder->run();

            $this->newLine();
            $this->info('🎉 Demo data created successfully!');
            $this->newLine();

            $this->info('You can now:');
            $this->info('1. Start your application server');
            $this->info('2. Login with the demo credentials');
            $this->info('3. Explore the dashboard filters with realistic data');
            $this->newLine();

            $this->info('💡 Tip: Use the "Active Only" filter in the dashboard to see how it affects the data display.');

        } catch (\Exception $e) {
            $this->error('❌ Failed to create demo data: '.$e->getMessage());
            $this->info('Please check the error details above and try again.');

            return 1;
        }

        return 0;
    }
}
