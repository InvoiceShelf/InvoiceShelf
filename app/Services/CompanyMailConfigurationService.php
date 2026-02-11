<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Config;

class CompanyMailConfigurationService
{
    /**
     * Configure mail settings for a specific company
     */
    public static function configureMailForCompany(int $companyId): void
    {
        // Get mail settings from company_settings
        $mailSettings = CompanySetting::getSettings([
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
        ], $companyId)->toArray();

        $driver = $mailSettings['mail_driver'] ?? null;
        if (empty($driver)) {
            $driver = Config::get('mail.default') ?: 'smtp';
        }

        if (! empty($driver)) {

            // Set default mailer
            Config::set('mail.default', $driver);

            // Configure based on driver
            switch ($driver) {
                case 'smtp':
                    $mailScheme = $mailSettings['mail_scheme'] ?? null;
                    $mailUrl = $mailSettings['mail_url'] ?? null;
                    $mailTimeout = $mailSettings['mail_timeout'] ?? null;
                    $mailLocalDomain = $mailSettings['mail_local_domain'] ?? null;

                    if ($mailScheme === '') {
                        $mailScheme = null;
                    }
                    if ($mailUrl === '') {
                        $mailUrl = null;
                    }
                    if ($mailTimeout === '') {
                        $mailTimeout = null;
                    }
                    if ($mailLocalDomain === '') {
                        $mailLocalDomain = null;
                    }

                    Config::set('mail.mailers.smtp.host', $mailSettings['mail_host'] ?? '127.0.0.1');
                    Config::set('mail.mailers.smtp.port', $mailSettings['mail_port'] ?? 2525);
                    Config::set('mail.mailers.smtp.username', $mailSettings['mail_username'] ?? null);
                    Config::set('mail.mailers.smtp.password', $mailSettings['mail_password'] ?? null);
                    Config::set('mail.mailers.smtp.encryption', $mailSettings['mail_encryption'] ?? 'none');
                    Config::set('mail.mailers.smtp.scheme', $mailScheme);
                    Config::set('mail.mailers.smtp.url', $mailUrl);
                    Config::set('mail.mailers.smtp.timeout', $mailTimeout);
                    Config::set('mail.mailers.smtp.local_domain', $mailLocalDomain);
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
                    Config::set('services.mailgun.scheme', $mailSettings['mail_mailgun_scheme'] ?? 'https');
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
    }
}
