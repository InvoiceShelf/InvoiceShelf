<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class CompanyMailConfigService
{
    private static array $mailSettingKeys = [
        'company_mail_driver',
        'company_mail_host',
        'company_mail_port',
        'company_mail_username',
        'company_mail_password',
        'company_mail_encryption',
        'company_mail_scheme',
        'company_mail_url',
        'company_mail_timeout',
        'company_mail_local_domain',
        'company_from_name',
        'company_from_mail',
        'company_mail_mailgun_domain',
        'company_mail_mailgun_secret',
        'company_mail_mailgun_endpoint',
        'company_mail_mailgun_scheme',
        'company_mail_ses_key',
        'company_mail_ses_secret',
        'company_mail_ses_region',
        'company_mail_sendmail_path',
    ];

    public static function apply(int $companyId): void
    {
        $useCustom = CompanySetting::getSetting('use_custom_mail_config', $companyId);

        if ($useCustom !== 'YES') {
            return;
        }

        $settings = [];
        foreach (self::$mailSettingKeys as $key) {
            $value = CompanySetting::getSetting($key, $companyId);
            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        $driver = $settings['company_mail_driver'] ?? null;

        if (empty($driver)) {
            return;
        }

        Config::set('mail.default', $driver);

        switch ($driver) {
            case 'smtp':
                Config::set('mail.mailers.smtp.host', $settings['company_mail_host'] ?? '127.0.0.1');
                Config::set('mail.mailers.smtp.port', $settings['company_mail_port'] ?? 2525);
                Config::set('mail.mailers.smtp.username', $settings['company_mail_username'] ?? null);
                Config::set('mail.mailers.smtp.password', $settings['company_mail_password'] ?? null);
                Config::set('mail.mailers.smtp.encryption', $settings['company_mail_encryption'] ?? 'none');
                Config::set('mail.mailers.smtp.scheme', $settings['company_mail_scheme'] ?? null);
                Config::set('mail.mailers.smtp.url', $settings['company_mail_url'] ?? null);
                Config::set('mail.mailers.smtp.timeout', $settings['company_mail_timeout'] ?? null);
                Config::set('mail.mailers.smtp.local_domain', $settings['company_mail_local_domain'] ?? null);
                break;

            case 'mailgun':
                Config::set('services.mailgun.domain', $settings['company_mail_mailgun_domain'] ?? null);
                Config::set('services.mailgun.secret', $settings['company_mail_mailgun_secret'] ?? null);
                Config::set('services.mailgun.endpoint', $settings['company_mail_mailgun_endpoint'] ?? 'api.mailgun.net');
                Config::set('services.mailgun.scheme', $settings['company_mail_mailgun_scheme'] ?? 'https');
                break;

            case 'ses':
                Config::set('services.ses.key', $settings['company_mail_ses_key'] ?? null);
                Config::set('services.ses.secret', $settings['company_mail_ses_secret'] ?? null);
                Config::set('services.ses.region', $settings['company_mail_ses_region'] ?? 'us-east-1');
                break;

            case 'sendmail':
                Config::set('mail.mailers.sendmail.path', $settings['company_mail_sendmail_path'] ?? '/usr/sbin/sendmail -bs -i');
                break;
        }

        if (! empty($settings['company_from_mail'])) {
            Config::set('mail.from.address', $settings['company_from_mail']);
        }
        if (! empty($settings['company_from_name'])) {
            Config::set('mail.from.name', $settings['company_from_name']);
        }

        // Purge the cached mailer so Laravel creates a new one with updated config
        Mail::purge($driver);
    }
}
