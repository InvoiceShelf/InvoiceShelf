<?php

namespace App\Platform\Mail\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Operations\Models\Setting;
use App\Rules\PublicHost;
use Aws\Sdk;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;

class MailConfigurationService implements MailConfigurator
{
    public const DEFAULT_DRIVER = 'sendmail';

    /**
     * Stands in for a stored secret in every configuration sent to the
     * browser. A save that returns it unchanged keeps the stored value.
     */
    public const SECRET_MASK = '********';

    /**
     * The only Mailgun API hosts there are (US and EU regions).
     */
    public const MAILGUN_ENDPOINTS = ['api.mailgun.net', 'api.eu.mailgun.net'];

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
        // The sendmail binary comes from MAIL_SENDMAIL_PATH alone. Whatever is
        // configured there is run as a command, so it never comes from a form.
        'sendmail' => [],
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

    /**
     * Fields that are credentials: never sent back once stored.
     */
    private const SECRET_FIELDS = [
        'mail_password',
        'mail_ses_secret',
        'mail_mailgun_secret',
        'mail_postmark_token',
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
        $current = Setting::getSettings($this->getGlobalSettingKeys())->all();

        Setting::setSettings($this->prepareSettingsForStorage($payload, self::GLOBAL_SCOPE, $current));
    }

    public function saveCompanyConfig(int|string $companyId, array $payload): void
    {
        if (($payload['use_custom_mail_config'] ?? 'YES') !== 'YES') {
            CompanySetting::setSettings([
                'use_custom_mail_config' => 'NO',
            ], $companyId);

            return;
        }

        $current = CompanySetting::getSettings($this->getCompanySettingKeys(), $companyId)->all();

        // company_settings.value is NOT NULL; a blank optional field is stored
        // as an empty string, which the apply step already reads as unset.
        $settings = array_map(
            fn (mixed $value): mixed => $value ?? '',
            $this->prepareSettingsForStorage($payload, self::COMPANY_SCOPE, $current)
        );

        CompanySetting::setSettings($settings + ['use_custom_mail_config' => 'YES'], $companyId);
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

    /**
     * The field of a company's stored custom configuration that points the
     * server at a private or reserved address, or null when there is none.
     *
     * Save-time validation holds new values to the public network; this checks
     * values saved before that rule existed.
     */
    public function companyPrivateTarget(int|string $companyId): ?string
    {
        $settings = CompanySetting::getSettings($this->getCompanySettingKeys(), $companyId)->all();

        if (($settings['use_custom_mail_config'] ?? 'NO') !== 'YES') {
            return null;
        }

        $stored = fn (string $field): string => (string) ($this->resolveStoredValue($settings, self::COMPANY_SCOPE, $field) ?? '');

        return match ($settings[$this->storedKey(self::COMPANY_SCOPE, 'mail_driver')] ?? null) {
            'smtp' => collect(['mail_host', 'mail_url'])->first(function (string $field) use ($stored): bool {
                $host = $stored($field) === '' ? null : PublicHost::hostOf($stored($field));

                return $host !== null && PublicHost::isBlocked($host);
            }),
            'mailgun' => in_array($stored('mail_mailgun_endpoint'), self::MAILGUN_ENDPOINTS, true) ? null : 'mail_mailgun_endpoint',
            default => null,
        };
    }

    /**
     * Rules for a submitted mail configuration.
     *
     * With $allowPrivateHosts off, every connection target must be publicly
     * routable: the SMTP host and DSN, and the Mailgun endpoint, which may then
     * only be one of Mailgun's own hosts. That is how company owners are held;
     * the super administrator keeps the private network, where a local relay is
     * an ordinary setup.
     */
    public function validationRules(?string $driver, bool $allowDisabledCustomConfig = false, bool $allowPrivateHosts = true): array
    {
        $availableDrivers = $this->getAvailableDrivers();
        $driver = $this->normalizeRequestedDriver($driver, $availableDrivers);

        $rules = [
            'mail_driver' => [
                'required',
                'string',
                Rule::in($availableDrivers),
            ],
            'from_name' => ['required', 'string'],
            'from_mail' => ['required', 'string', 'email'],
        ];

        if ($allowDisabledCustomConfig) {
            $rules['use_custom_mail_config'] = [
                'required',
                'string',
                Rule::in(['YES', 'NO']),
            ];
        }

        $publicOnly = $allowPrivateHosts ? [] : [new PublicHost];

        return array_merge($rules, match ($driver) {
            'smtp' => [
                'mail_host' => ['required', 'string', ...$publicOnly],
                'mail_port' => ['required', 'integer'],
                'mail_username' => ['nullable', 'string'],
                'mail_password' => ['nullable', 'string'],
                'mail_encryption' => ['nullable', 'string', Rule::in(['none', 'tls', 'ssl'])],
                'mail_scheme' => ['nullable', 'string', Rule::in(['smtp', 'smtps'])],
                'mail_url' => ['nullable', 'string', ...$publicOnly],
                'mail_timeout' => ['nullable', 'integer'],
                'mail_local_domain' => ['nullable', 'string'],
            ],
            'ses' => [
                'mail_ses_key' => ['required', 'string'],
                'mail_ses_secret' => ['required', 'string'],
                'mail_ses_region' => ['nullable', 'string'],
            ],
            'mailgun' => [
                'mail_mailgun_domain' => ['required', 'string'],
                'mail_mailgun_secret' => ['required', 'string'],
                'mail_mailgun_endpoint' => ['required', 'string', ...($allowPrivateHosts ? [] : [Rule::in(self::MAILGUN_ENDPOINTS)])],
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
            $value = $this->resolveStoredValue($settings, $scope, $field);

            $payload[$field] = $this->isSecret($field) && filled($value) ? self::SECRET_MASK : $value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $current  The settings stored now, so a secret sent back masked keeps its value.
     */
    private function prepareSettingsForStorage(array $payload, string $scope, array $current): array
    {
        $driver = $this->normalizeRequestedDriver($payload['mail_driver'] ?? null, $this->getAvailableDrivers());

        $settings = [
            $this->storedKey($scope, 'mail_driver') => $driver,
            $this->storedKey($scope, 'from_name') => $payload['from_name'] ?? $this->getDefaultValue('from_name'),
            $this->storedKey($scope, 'from_mail') => $payload['from_mail'] ?? $this->getDefaultValue('from_mail'),
        ];

        foreach (self::DRIVER_FIELDS[$driver] as $field) {
            $value = $payload[$field] ?? $this->getDefaultValue($field);

            if ($this->isSecret($field) && $value === self::SECRET_MASK) {
                $value = $this->resolveStoredValue($current, $scope, $field);
            }

            $settings[$this->storedKey($scope, $field)] = $this->normalizeStoredValue($field, $value);
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

    private function isSecret(string $field): bool
    {
        return in_array($field, self::SECRET_FIELDS, true);
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
