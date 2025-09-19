<?php

namespace App\Providers;

use App\Models\Setting;
use App\Space\InstallUtils;
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
            // Get mail settings from database
            $mailSettings = Setting::getSettings([
                'mail_driver',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_scheme',
                'mail_url',
                'mail_timeout',
                'mail_local_domain',
                'from_name',
                'from_mail',
                'mail_mailgun_domain',
                'mail_mailgun_secret',
                'mail_mailgun_endpoint',
                'mail_mailgun_scheme',
                'mail_ses_key',
                'mail_ses_secret',
                'mail_ses_region',
                'mail_sendmail_path',
            ]);

            if (! empty($mailSettings['mail_driver'])) {
                $driver = $mailSettings['mail_driver'];

                // Set default mailer
                Config::set('mail.default', $driver);

                // Configure based on driver
                switch ($driver) {
                    case 'smtp':
                        Config::set('mail.mailers.smtp.host', $mailSettings['mail_host'] ?? '127.0.0.1');
                        Config::set('mail.mailers.smtp.port', $mailSettings['mail_port'] ?? 2525);
                        Config::set('mail.mailers.smtp.username', $mailSettings['mail_username'] ?? null);
                        Config::set('mail.mailers.smtp.password', $mailSettings['mail_password'] ?? null);
                        Config::set('mail.mailers.smtp.encryption', $mailSettings['mail_encryption'] ?? 'none');
                        Config::set('mail.mailers.smtp.scheme', $mailSettings['mail_scheme'] ?? null);
                        Config::set('mail.mailers.smtp.url', $mailSettings['mail_url'] ?? null);
                        Config::set('mail.mailers.smtp.timeout', $mailSettings['mail_timeout'] ?? null);
                        Config::set('mail.mailers.smtp.local_domain', $mailSettings['mail_local_domain'] ?? null);
                        break;

                    case 'mailgun':
                        Config::set('mail.mailers.mailgun.domain', $mailSettings['mail_mailgun_domain'] ?? null);
                        Config::set('mail.mailers.mailgun.secret', $mailSettings['mail_mailgun_secret'] ?? null);
                        Config::set('mail.mailers.mailgun.endpoint', $mailSettings['mail_mailgun_endpoint'] ?? 'api.mailgun.net');
                        Config::set('mail.mailers.mailgun.scheme', $mailSettings['mail_mailgun_scheme'] ?? 'https');

                        // Also set services config for mailgun
                        Config::set('services.mailgun.domain', $mailSettings['mail_mailgun_domain'] ?? null);
                        Config::set('services.mailgun.secret', $mailSettings['mail_mailgun_secret'] ?? null);
                        Config::set('services.mailgun.endpoint', $mailSettings['mail_mailgun_endpoint'] ?? 'api.mailgun.net');
                        break;

                    case 'ses':
                        Config::set('services.ses.key', $mailSettings['mail_ses_key'] ?? null);
                        Config::set('services.ses.secret', $mailSettings['mail_ses_secret'] ?? null);
                        Config::set('services.ses.region', $mailSettings['mail_ses_region'] ?? 'us-east-1');
                        break;

                    case 'sendmail':
                        Config::set('mail.mailers.sendmail.path', $mailSettings['mail_sendmail_path'] ?? '/usr/sbin/sendmail -bs -i');
                        break;
                }

                // Set global from address and name
                if (! empty($mailSettings['from_mail'])) {
                    Config::set('mail.from.address', $mailSettings['from_mail']);
                }
                if (! empty($mailSettings['from_name'])) {
                    Config::set('mail.from.name', $mailSettings['from_name']);
                }
            }
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
            // Get the default file disk from database
            $fileDisk = \App\Models\FileDisk::whereSetAsDefault(true)->first();

            if ($fileDisk) {
                $fileDisk->setConfig();
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available (during installation, migrations, etc.)
            // This prevents the application from breaking during setup
        }
    }
}
