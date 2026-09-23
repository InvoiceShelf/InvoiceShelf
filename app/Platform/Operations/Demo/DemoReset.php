<?php

namespace App\Platform\Operations\Demo;

use App\Platform\Modules\Marketplace\MarketplaceInstaller;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\CurrenciesTableSeeder;
use Database\Seeders\PublicDemoSeeder;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

/**
 * Rebuilds the public demo from nothing: an empty database, no uploaded
 * files, the demo company with realistic records, the pinned modules and
 * their sample data, and a customer who can sign in to the portal.
 *
 * The site is in maintenance mode while this runs and always comes back up,
 * even when a step fails; a failure is rethrown for the scheduler to report.
 */
class DemoReset
{
    /**
     * Directories under storage/app that survive a reset: custom PDF
     * templates, and `public`, which is emptied rather than removed because
     * the public/storage link points at it.
     */
    private const KEPT_DIRECTORIES = ['templates', 'public'];

    public function __construct(
        private readonly MarketplaceInstaller $marketplace,
    ) {}

    /**
     * @param  callable(string): void  $report  told about each step as it starts
     */
    public function run(callable $report): void
    {
        $report('Taking the site down');
        Artisan::call('down', ['--retry' => 60]);

        try {
            $report('Emptying the database');
            $this->artisan('migrate:fresh', ['--force' => true]);

            $report('Removing uploaded files');
            $this->removeFiles();

            $report('Adding currencies and countries');
            $this->seed(CurrenciesTableSeeder::class);
            $this->seed(CountriesTableSeeder::class);

            $report('Creating the demo company');
            $this->seed(PublicDemoSeeder::class);
            $this->seed(RealisticDemoSeeder::class);

            foreach (DemoMode::modules() as $module) {
                $report("Installing {$module['slug']} {$module['version']}");
                $this->installModule($module['slug'], $module['version']);
            }

            $report('Opening the customer portal');
            PublicDemoSeeder::openPortal();

            $this->artisan('optimize:clear');
        } finally {
            $report('Bringing the site back up');
            Artisan::call('up');
        }
    }

    /**
     * Install a pinned module release, from disk when that exact version is
     * already there and from the marketplace otherwise, then give it sample
     * data when it ships a demo seeder (`Modules\{Name}\Demo\DemoSeeder`).
     */
    private function installModule(string $slug, string $version): void
    {
        Module::scan();
        $onDisk = collect(Module::all())->first(fn ($module) => $module->get('slug') === $slug && $module->get('version') === $version);

        if ($onDisk !== null) {
            $this->artisan('install:module', ['module' => $onDisk->getName(), 'version' => $version]);
            $name = $onDisk->getName();
        } else {
            $result = $this->marketplace->install($slug, $version, 'stable');

            if (! $result['success']) {
                throw new RuntimeException("Installing {$slug} {$version} failed: ".($result['error'] ?? 'unknown error'));
            }

            Module::scan();
            $name = collect(Module::all())->first(fn ($module) => $module->get('slug') === $slug)?->getName();
        }

        $seeder = "Modules\\{$name}\\Demo\\DemoSeeder";

        // db:seed cannot say which company to fill, so the seeder is called
        // directly: run(int $companyId), its services resolved by the container.
        if ($name !== null && class_exists($seeder)) {
            app($seeder)->setContainer(app())->__invoke(['companyId' => DemoMode::company()->id]);
        }
    }

    /**
     * Everything visitors uploaded: logos, avatars, receipts, attachments and
     * backups. The SQLite database, the key files and custom templates stay.
     */
    private function removeFiles(): void
    {
        foreach (File::directories(storage_path('app')) as $directory) {
            if (! in_array(basename($directory), self::KEPT_DIRECTORIES, true)) {
                File::deleteDirectory($directory);
            }
        }

        foreach ([storage_path('app/public'), public_path('media')] as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::directories($root) as $directory) {
                File::deleteDirectory($directory);
            }

            foreach (File::files($root) as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    private function seed(string $seeder): void
    {
        $this->artisan('db:seed', ['--class' => $seeder, '--force' => true]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisan(string $command, array $parameters = []): void
    {
        if (Artisan::call($command, $parameters) !== 0) {
            throw new RuntimeException("{$command} failed: ".trim(Artisan::output()));
        }
    }
}
