<?php

namespace App\Providers;

use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\FileDiskService;
use App\Services\MailConfigurationService;
use App\Services\Setup\InstallUtils;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppConfigProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Check if database is available
        if (! InstallUtils::isDbCreated()) {
            return;
        }

        $this->configureMailFromDatabase();
        $this->configurePDFFromDatabase();
        $this->configureFileSystemFromDatabase();
    }

    /**
     * Configure mail settings from database
     */
    protected function configureMailFromDatabase(): void
    {
        try {
            app(MailConfigurationService::class)->applyGlobalConfig();
        } catch (\Exception $e) {
            // Silently fail if database is not available (during installation, migrations, etc.)
            // This prevents the application from breaking during setup
        }
    }

    /**
     * Configure PDF settings from database
     */
    protected function configurePDFFromDatabase(): void
    {
        try {
            // Get PDF settings from database
            $pdfSettings = Setting::getSettings([
                'pdf_driver',
                'gotenberg_host',
                'gotenberg_papersize',
                'gotenberg_margins',
            ]);

            if (! empty($pdfSettings['pdf_driver'])) {
                $driver = $pdfSettings['pdf_driver'];

                // Set PDF driver
                Config::set('pdf.driver', $driver);

                // Configure based on driver
                switch ($driver) {
                    case 'gotenberg':
                        if (! empty($pdfSettings['gotenberg_host'])) {
                            Config::set('pdf.connections.gotenberg.host', $pdfSettings['gotenberg_host']);
                        }
                        if (! empty($pdfSettings['gotenberg_papersize'])) {
                            Config::set('pdf.connections.gotenberg.papersize', $pdfSettings['gotenberg_papersize']);
                        }
                        if (! empty($pdfSettings['gotenberg_margins'])) {
                            Config::set('pdf.connections.gotenberg.margins', $pdfSettings['gotenberg_margins']);
                        }
                        break;

                    case 'dompdf':
                        // dompdf doesn't have additional configuration in the current setup
                        break;
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available (during installation, migrations, etc.)
            // This prevents the application from breaking during setup
        }
    }

    /**
     * Configure file system settings from database
     */
    protected function configureFileSystemFromDatabase(): void
    {
        try {
            $fileDisk = FileDisk::whereSetAsDefault(true)->first();

            if (! $fileDisk) {
                return;
            }

            $diskName = app(FileDiskService::class)->registerDisk($fileDisk);

            // Point Spatie Media Library at the resolved disk
            config(['media-library.disk_name' => $diskName]);
        } catch (\Exception $e) {
            // Silently fail if database is not available (during installation, migrations, etc.)
        }
    }
}
