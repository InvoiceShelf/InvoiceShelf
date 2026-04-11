<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Setting;
use Aws\Sdk;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;

class MailConfigurationService
{
    public const DEFAULT_DRIVER = 'sendmail';

    private const GLOBAL_SCOPE = 'global';

    private const COMPANY_SCOPE = 'company';

    private const DRIVER_ORDER = [
        'sendmail',
        'smtp',
        'mail',
        'ses',
        'mailgun',
        'postmark',
    ];

    private const DRIVER_FIELDS = [
        'smtp' => [
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_scheme',
            'mail_url',
            'mail_timeout',
            'mail_local_domain',
        ],
        'mail' => [],
        'sendmail' => [
            'mail_sendmail_path',
        ],
        'ses' => [
            'mail_ses_key',
            'mail_ses_secret',
            'mail_ses_region',
        ],
        'mailgun' => [
            'mail_mailgun_domain',
            'mail_mailgun_secret',
            'mail_mailgun_endpoint',
            'mail_mailgun_scheme',
        ],
        'postmark' => [
            'mail_postmark_token',
            'mail_postmark_message_stream_id',
        ],
    ];

    private const BASE_FIELDS = [
        'mail_driver',
        'from_name',
        'from_mail',
    ];

    public function getAvailableDrivers(): array
    {
        return array_values(array_filter(self::DRIVER_ORDER, fn (string $driver) => $this->isDriverAvailable($driver)));
    }

    public function getGlobalConfig(): array
    {
        return $this->buildConfigPayload(
            Setting::getSettings($this->getGlobalSettingKeys())->all(),
            self::GLOBAL_SCOPE
        );
    }

    public function getCompanyConfig(int|string $companyId): array
    {
        $settings = CompanySetting::getSettings($this->getCompanySettingKeys(), $companyId)->all();

        return array_merge(
            [
                'use_custom_mail_config' => $settings['use_custom_mail_config'] ?? 'NO',
            ],
            $this->buildConfigPayload($settings, self::COMPANY_SCOPE)
        );
    }

    public function getDefaultConfig(): array
    {
        return [
            'from_name' => $this->getDefaultValue('from_name'),
            'from_mail' => $this->getDefaultValue('from_mail'),
        ];
    }

    public function saveGlobalConfig(array $payload): void
    {
        Setting::setSettings($this->prepareSettingsForStorage($payload, self::GLOBAL_SCOPE));
    }

    public function saveCompanyConfig(int|string $companyId, array $payload): void
    {
        if (($payload['use_custom_mail_config'] ?? 'YES') !== 'YES') {
            CompanySetting::setSettings([
                'use_custom_mail_config' => 'NO',
            ], $companyId);

            return;
        }

        CompanySetting::setSettings(
            $this->prepareSettingsForStorage($payload, self::COMPANY_SCOPE) + [
                'use_custom_mail_config' => 'YES',
            ],
            $companyId
        );
    }

    public function applyGlobalConfig(): void
    {
        $settings = Setting::getSettings($this->getGlobalSettingKeys())->all();

        $this->applyStoredSettings($settings, self::GLOBAL_SCOPE);
    }

    public function applyCompanyConfig(int|string $companyId): void
    {
        $settings = CompanySetting::getSettings($this->getCompanySettingKeys(), $companyId)->all();

        if (($settings['use_custom_mail_config'] ?? 'NO') !== 'YES') {
            return;
        }

        $this->applyStoredSettings($settings, self::COMPANY_SCOPE);
    }

    public function validationRules(?string $driver, bool $allowDisabledCustomConfig = false): array
    {
        $availableDrivers = $this->getAvailableDrivers();
        $driver = $this->normalizeRequestedDriver($driver, $availableDrivers);

        $rules = [
            'mail_driver' => [
                'required',
                'string',
                Rule::in($availableDrivers),
            ],
            'from_name' => [
                'required',
                'string',
            ],
            'from_mail' => [
                'required',
                'string',
                'email',
            ],
        ];

        if ($allowDisabledCustomConfig) {
            $rules['use_custom_mail_config'] = [
                'required',
                'string',
                Rule::in(['YES', 'NO']),
            ];
        }

        return array_merge($rules, match ($driver) {
            'smtp' => [
                'mail_host' => ['required', 'string'],
                'mail_port' => ['required', 'integer'],
                'mail_username' => ['nullable', 'string'],
                'mail_password' => ['nullable', 'string'],
                'mail_encryption' => ['nullable', 'string', Rule::in(['none', 'tls', 'ssl'])],
                'mail_scheme' => ['nullable', 'string', Rule::in(['smtp', 'smtps'])],
                'mail_url' => ['nullable', 'string'],
                'mail_timeout' => ['nullable', 'integer'],
                'mail_local_domain' => ['nullable', 'string'],
            ],
            'sendmail' => [
                'mail_sendmail_path' => ['nullable', 'string'],
            ],
            'ses' => [
                'mail_ses_key' => ['required', 'string'],
                'mail_ses_secret' => ['required', 'string'],
                'mail_ses_region' => ['nullable', 'string'],
            ],
            'mailgun' => [
                'mail_mailgun_domain' => ['required', 'string'],
                'mail_mailgun_secret' => ['required', 'string'],
                'mail_mailgun_endpoint' => ['required', 'string'],
                'mail_mailgun_scheme' => ['nullable', 'string', Rule::in(['https', 'api'])],
            ],
            'postmark' => [
                'mail_postmark_token' => ['required', 'string'],
                'mail_postmark_message_stream_id' => ['nullable', 'string'],
            ],
            default => [],
        });
    }

    public function getGlobalSettingKeys(): array
    {
        return $this->buildSettingKeys(self::GLOBAL_SCOPE, true);
    }

    public function getCompanySettingKeys(): array
    {
        return array_merge(
            $this->buildSettingKeys(self::COMPANY_SCOPE, true),
            ['use_custom_mail_config']
        );
    }

    private function buildSettingKeys(string $scope, bool $includeAllDrivers): array
    {
        $fields = self::BASE_FIELDS;

        if ($includeAllDrivers) {
            foreach (self::DRIVER_FIELDS as $driverFields) {
                $fields = array_merge($fields, $driverFields);
            }
        }

        return array_values(array_unique(array_map(
            fn (string $field) => $this->storedKey($scope, $field),
            $fields
        )));
    }

    private function buildConfigPayload(array $settings, string $scope): array
    {
        $driver = $this->normalizeStoredDriver(
            $this->resolveStoredValue($settings, $scope, 'mail_driver')
        );

        $payload = [
            'mail_driver' => $driver,
            'from_name' => $this->resolveStoredValue($settings, $scope, 'from_name'),
            'from_mail' => $this->resolveStoredValue($settings, $scope, 'from_mail'),
        ];

        foreach (self::DRIVER_FIELDS[$driver] as $field) {
            $payload[$field] = $this->resolveStoredValue($settings, $scope, $field);
        }

        return $payload;
    }

    private function prepareSettingsForStorage(array $payload, string $scope): array
    {
        $driver = $this->normalizeRequestedDriver($payload['mail_driver'] ?? null, $this->getAvailableDrivers());

        $settings = [
            $this->storedKey($scope, 'mail_driver') => $driver,
            $this->storedKey($scope, 'from_name') => $payload['from_name'] ?? $this->getDefaultValue('from_name'),
            $this->storedKey($scope, 'from_mail') => $payload['from_mail'] ?? $this->getDefaultValue('from_mail'),
        ];

        foreach (self::DRIVER_FIELDS[$driver] as $field) {
            $settings[$this->storedKey($scope, $field)] = $this->normalizeStoredValue(
                $field,
                $payload[$field] ?? $this->getDefaultValue($field)
            );
        }

        return $settings;
    }

    private function applyStoredSettings(array $settings, string $scope): void
    {
        $driver = $settings[$this->storedKey($scope, 'mail_driver')] ?? null;

        if (! $driver || ! in_array($driver, self::DRIVER_ORDER, true)) {
            return;
        }

        Config::set('mail.default', $driver);

        match ($driver) {
            'smtp' => $this->applySmtpSettings($settings, $scope),
            'sendmail' => $this->applySendmailSettings($settings, $scope),
            'ses' => $this->applySesSettings($settings, $scope),
            'mailgun' => $this->applyMailgunSettings($settings, $scope),
            'postmark' => $this->applyPostmarkSettings($settings, $scope),
            default => null,
        };

        Config::set('mail.from.address', $this->resolveStoredValue($settings, $scope, 'from_mail'));
        Config::set('mail.from.name', $this->resolveStoredValue($settings, $scope, 'from_name'));

        Mail::purge($driver);
    }

    private function applySmtpSettings(array $settings, string $scope): void
    {
        Config::set('mail.mailers.smtp.host', $this->resolveStoredValue($settings, $scope, 'mail_host'));
        Config::set('mail.mailers.smtp.port', $this->resolveStoredValue($settings, $scope, 'mail_port'));
        Config::set('mail.mailers.smtp.username', $this->resolveStoredValue($settings, $scope, 'mail_username'));
        Config::set('mail.mailers.smtp.password', $this->resolveStoredValue($settings, $scope, 'mail_password'));
        Config::set('mail.mailers.smtp.encryption', $this->resolveStoredValue($settings, $scope, 'mail_encryption'));
        Config::set('mail.mailers.smtp.scheme', $this->nullIfBlank($this->resolveStoredValue($settings, $scope, 'mail_scheme')));
        Config::set('mail.mailers.smtp.url', $this->nullIfBlank($this->resolveStoredValue($settings, $scope, 'mail_url')));
        Config::set('mail.mailers.smtp.timeout', $this->nullIfBlank($this->resolveStoredValue($settings, $scope, 'mail_timeout')));
        Config::set('mail.mailers.smtp.local_domain', $this->nullIfBlank($this->resolveStoredValue($settings, $scope, 'mail_local_domain')));
    }

    private function applySendmailSettings(array $settings, string $scope): void
    {
        Config::set('mail.mailers.sendmail.path', $this->resolveStoredValue($settings, $scope, 'mail_sendmail_path'));
    }

    private function applySesSettings(array $settings, string $scope): void
    {
        Config::set('services.ses.key', $this->resolveStoredValue($settings, $scope, 'mail_ses_key'));
        Config::set('services.ses.secret', $this->resolveStoredValue($settings, $scope, 'mail_ses_secret'));
        Config::set('services.ses.region', $this->resolveStoredValue($settings, $scope, 'mail_ses_region'));
    }

    private function applyMailgunSettings(array $settings, string $scope): void
    {
        $domain = $this->resolveStoredValue($settings, $scope, 'mail_mailgun_domain');
        $secret = $this->resolveStoredValue($settings, $scope, 'mail_mailgun_secret');
        $endpoint = $this->resolveStoredValue($settings, $scope, 'mail_mailgun_endpoint');
        $scheme = $this->resolveStoredValue($settings, $scope, 'mail_mailgun_scheme');

        Config::set('mail.mailers.mailgun.domain', $domain);
        Config::set('mail.mailers.mailgun.secret', $secret);
        Config::set('mail.mailers.mailgun.endpoint', $endpoint);
        Config::set('mail.mailers.mailgun.scheme', $scheme);

        Config::set('services.mailgun.domain', $domain);
        Config::set('services.mailgun.secret', $secret);
        Config::set('services.mailgun.endpoint', $endpoint);
        Config::set('services.mailgun.scheme', $scheme);
    }

    private function applyPostmarkSettings(array $settings, string $scope): void
    {
        $token = $this->resolveStoredValue($settings, $scope, 'mail_postmark_token');
        $messageStreamId = $this->nullIfBlank($this->resolveStoredValue($settings, $scope, 'mail_postmark_message_stream_id'));

        Config::set('services.postmark.token', $token);
        Config::set('mail.mailers.postmark.token', $token);
        Config::set('mail.mailers.postmark.message_stream_id', $messageStreamId);
    }

    private function resolveStoredValue(array $settings, string $scope, string $field): mixed
    {
        $key = $this->storedKey($scope, $field);

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return $this->getDefaultValue($field);
    }

    private function storedKey(string $scope, string $field): string
    {
        return $scope === self::COMPANY_SCOPE ? "company_{$field}" : $field;
    }

    private function getDefaultValue(string $field): mixed
    {
        return match ($field) {
            'mail_driver' => $this->normalizeStoredDriver(config('mail.default')),
            'from_name' => config('mail.from.name'),
            'from_mail' => config('mail.from.address'),
            'mail_host' => config('mail.mailers.smtp.host', '127.0.0.1'),
            'mail_port' => config('mail.mailers.smtp.port', 587),
            'mail_username', 'mail_password', 'mail_scheme', 'mail_url', 'mail_timeout', 'mail_local_domain' => '',
            'mail_encryption' => config('mail.mailers.smtp.encryption', 'none'),
            'mail_sendmail_path' => config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'),
            'mail_ses_key' => config('services.ses.key', ''),
            'mail_ses_secret' => config('services.ses.secret', ''),
            'mail_ses_region' => config('services.ses.region', 'us-east-1'),
            'mail_mailgun_domain' => config('services.mailgun.domain', ''),
            'mail_mailgun_secret' => config('services.mailgun.secret', ''),
            'mail_mailgun_endpoint' => config('services.mailgun.endpoint', 'api.mailgun.net'),
            'mail_mailgun_scheme' => config('mail.mailers.mailgun.scheme', config('services.mailgun.scheme', 'https')),
            'mail_postmark_token' => config('services.postmark.token', ''),
            'mail_postmark_message_stream_id' => config('mail.mailers.postmark.message_stream_id', ''),
            default => '',
        };
    }

    private function normalizeRequestedDriver(?string $driver, array $availableDrivers): string
    {
        if ($driver && in_array($driver, $availableDrivers, true)) {
            return $driver;
        }

        return $availableDrivers[0] ?? self::DEFAULT_DRIVER;
    }

    private function normalizeStoredDriver(?string $driver): string
    {
        $availableDrivers = $this->getAvailableDrivers();

        if ($driver && in_array($driver, $availableDrivers, true)) {
            return $driver;
        }

        return $availableDrivers[0] ?? self::DEFAULT_DRIVER;
    }

    private function normalizeStoredValue(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return match ($field) {
            'mail_port', 'mail_timeout' => $value === '' ? null : $value,
            'mail_scheme',
            'mail_url',
            'mail_local_domain',
            'mail_postmark_message_stream_id' => $value === '' ? '' : $value,
            'mail_mailgun_endpoint' => $value === '' ? 'api.mailgun.net' : $value,
            'mail_mailgun_scheme' => $value === '' ? 'https' : $value,
            'mail_sendmail_path' => $value === '' ? '/usr/sbin/sendmail -bs -i' : $value,
            'mail_ses_region' => $value === '' ? 'us-east-1' : $value,
            'mail_encryption' => $value === '' ? 'none' : $value,
            default => $value,
        };
    }

    private function nullIfBlank(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    private function isDriverAvailable(string $driver): bool
    {
        return match ($driver) {
            'smtp', 'mail', 'sendmail' => true,
            'ses' => class_exists(Sdk::class),
            'mailgun' => class_exists(MailgunTransportFactory::class)
                && class_exists(HttpClient::class),
            'postmark' => class_exists(PostmarkTransportFactory::class)
                && class_exists(HttpClient::class),
            default => false,
        };
    }
}
