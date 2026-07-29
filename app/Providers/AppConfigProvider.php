<?php

namespace App\Providers;

use App\Models\FileDisk;
use App\Models\Setting;
use App\Services\Mail\MailConfigurationService;
use App\Services\Storage\FileDiskService;
use App\Support\Setup\InstallUtils;
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
            $pageSettings = [
                'pdf_paper_width' => 'pdf.page.paper_width',
                'pdf_paper_height' => 'pdf.page.paper_height',
                'pdf_orientation' => 'pdf.page.orientation',
                'pdf_margin_top' => 'pdf.page.margin_top',
                'pdf_margin_right' => 'pdf.page.margin_right',
                'pdf_margin_bottom' => 'pdf.page.margin_bottom',
                'pdf_margin_left' => 'pdf.page.margin_left',
            ];

            $pdfSettings = Setting::getSettings(array_merge(
                ['pdf_driver', 'gotenberg_host', 'pdf_page_numbers'],
                array_keys($pageSettings),
            ));

            if (! empty($pdfSettings['pdf_driver'])) {
                Config::set('pdf.driver', $pdfSettings['pdf_driver']);

                if ($pdfSettings['pdf_driver'] === 'gotenberg' && ! empty($pdfSettings['gotenberg_host'])) {
                    Config::set('pdf.connections.gotenberg.host', $pdfSettings['gotenberg_host']);
                }
            }

            // Page geometry is applied regardless of driver. Note the isset guard
            // rather than !empty: a saved margin of "0mm" is a deliberate choice,
            // and !empty() would discard it and silently fall back to the default.
            foreach ($pageSettings as $setting => $configKey) {
                if (isset($pdfSettings[$setting]) && trim((string) $pdfSettings[$setting]) !== '') {
                    Config::set($configKey, $pdfSettings[$setting]);
                }
            }

            // Stored as a string, so cast rather than passing '0' through as a
            // truthy value.
            if (isset($pdfSettings['pdf_page_numbers'])) {
                Config::set(
                    'pdf.page.page_numbers',
                    filter_var($pdfSettings['pdf_page_numbers'], FILTER_VALIDATE_BOOLEAN)
                );
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
