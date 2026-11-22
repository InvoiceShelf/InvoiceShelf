<?php

namespace App\Platform\Operations\Demo;

use App\Platform\Modules\Marketplace\MarketplaceClient;
use App\Platform\Modules\Marketplace\MarketplaceInstaller;
use App\Platform\Modules\Runtime\ModuleCompatibility;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\CurrenciesTableSeeder;
use Database\Seeders\PublicDemoSeeder;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use RuntimeException;
use Throwable;

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
        private readonly MarketplaceClient $catalog,
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
                $this->installDemoModule($module['slug'], $module['version'], $report);
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
    /**
     * Install one DEMO_MODULES entry: the pinned release, or the newest one
     * that fits this InvoiceShelf when none is pinned. A module that cannot be
     * installed is reported and left out, so one bad module release never
     * keeps the demo down.
     */
    private function installDemoModule(string $slug, ?string $version, callable $report): void
    {
        try {
            $version ??= $this->newestFittingVersion($slug);

            if ($version === null) {
                $report("Leaving out {$slug}: no release fits this version");

                return;
            }

            $report("Installing {$slug} {$version}");
            $this->installModule($slug, $version);
        } catch (Throwable $error) {
            report($error);
            $report("Leaving out {$slug}: {$error->getMessage()}");
        }
    }

    /**
     * The marketplace's newest release of a module, when it fits this
     * InvoiceShelf; null when it does not, or there is none.
     */
    private function newestFittingVersion(string $slug): ?string
    {
        $release = $this->catalog->module($slug)->json('module.release');

        if (! is_array($release) || ! is_string($release['version'] ?? null)) {
            return null;
        }

        return ModuleCompatibility::problems($release['compatibility'] ?? null) === [] ? $release['version'] : null;
    }

    private function installModule(string $slug, string $version): void
    {
        $onDisk = collect($this->modulesOnDisk())->first(fn ($module) => $module->get('slug') === $slug && $module->get('version') === $version);

        if ($onDisk !== null) {
            $this->artisan('install:module', ['module' => $onDisk->getName(), 'version' => $version]);
            $name = $onDisk->getName();
        } else {
            $result = $this->marketplace->install($slug, $version, 'stable');

            if (! $result['success']) {
                throw new RuntimeException("Installing {$slug} {$version} failed: ".($result['error'] ?? 'unknown error'));
            }

            $name = collect($this->modulesOnDisk())->first(fn ($module) => $module->get('slug') === $slug)?->getName();
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
    /**
     * The modules on disk right now. nwidart keeps its first non-empty scan
     * for the rest of the process (outside unit tests), so without the reset
     * a module the marketplace installed a moment ago is missing from the list
     * and gets no sample data: every pinned module after the first.
     *
     * @return array<string, \Nwidart\Modules\Module>
     */
    private function modulesOnDisk(): array
    {
        Module::resetModules();

        return Module::all();
    }

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
