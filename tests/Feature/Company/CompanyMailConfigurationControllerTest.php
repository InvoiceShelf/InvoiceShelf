<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $user = User::query()->find(1);
    $this->companyId = $user->companies()->first()->id;

    $this->withHeaders([
        'company' => $this->companyId,
    ]);

    Sanctum::actingAs($user, ['*']);
});

test('get company mail configuration falls back to global config defaults', function () {
    Setting::setSettings([
        'mail_driver' => 'mail',
        'from_name' => 'Global Mailer',
        'from_mail' => 'global@example.com',
    ]);

    app(MailConfigurationService::class)->applyGlobalConfig();

    getJson('/api/v1/company/mail/company-config')
        ->assertOk()
        ->assertJson([
            'use_custom_mail_config' => 'NO',
            'mail_driver' => 'mail',
            'from_name' => 'Global Mailer',
            'from_mail' => 'global@example.com',
        ]);
});

test('save company mail configuration persists postmark settings', function () {
    postJson('/api/v1/company/mail/company-config', [
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'postmark',
        'mail_postmark_token' => 'company-postmark-token',
        'mail_postmark_message_stream_id' => 'broadcasts',
        'from_name' => 'Company Mailer',
        'from_mail' => 'company@example.com',
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $this->companyId,
        'option' => 'use_custom_mail_config',
        'value' => 'YES',
    ]);

    $this->assertDatabaseHas('company_settings', [
        'company_id' => $this->companyId,
        'option' => 'company_mail_postmark_token',
        'value' => 'company-postmark-token',
    ]);
});

test('disabling company mail configuration only flips the custom-config toggle', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'postmark',
        'company_mail_postmark_token' => 'existing-company-token',
    ], $this->companyId);

    postJson('/api/v1/company/mail/company-config', [
        'use_custom_mail_config' => 'NO',
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect(CompanySetting::getSetting('use_custom_mail_config', $this->companyId))->toBe('NO');
    expect(CompanySetting::getSetting('company_mail_postmark_token', $this->companyId))
        ->toBe('existing-company-token');
});

test('company mail runtime apply maps postmark settings into laravel config', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'postmark',
        'company_mail_postmark_token' => 'runtime-postmark-token',
        'company_mail_postmark_message_stream_id' => 'outbound',
        'company_from_name' => 'Runtime Mailer',
        'company_from_mail' => 'runtime@example.com',
    ], $this->companyId);

    app(MailConfigurationService::class)->applyCompanyConfig($this->companyId);

    expect(config('mail.default'))->toBe('postmark');
    expect(config('services.postmark.token'))->toBe('runtime-postmark-token');
    expect(config('mail.mailers.postmark.message_stream_id'))->toBe('outbound');
    expect(config('mail.from.name'))->toBe('Runtime Mailer');
    expect(config('mail.from.address'))->toBe('runtime@example.com');
});

test('a member who is not the owner cannot read the mail configuration', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'postmark',
        'company_mail_postmark_token' => 'owner-only-token',
    ], $this->companyId);

    $member = User::factory()->create(['email' => 'member@example.com', 'role' => 'user']);
    $member->companies()->attach($this->companyId);
    Sanctum::actingAs($member, ['*']);

    getJson('/api/v1/company/mail/company-config')->assertForbidden();
    getJson('/api/v1/company/mail/config')->assertForbidden();
});

test('the mail configuration never sends a stored secret back', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'postmark',
        'company_mail_postmark_token' => 'stored-postmark-token',
        'company_mail_postmark_message_stream_id' => 'outbound',
    ], $this->companyId);

    getJson('/api/v1/company/mail/company-config')
        ->assertOk()
        ->assertJson([
            'mail_postmark_token' => MailConfigurationService::SECRET_MASK,
            'mail_postmark_message_stream_id' => 'outbound',
        ])
        ->assertDontSee('stored-postmark-token');
});

test('an unset secret reads back empty rather than masked', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_host' => 'smtp.example.com',
        'company_mail_password' => '',
    ], $this->companyId);

    getJson('/api/v1/company/mail/company-config')
        ->assertOk()
        ->assertJson(['mail_password' => '']);
});

test('saving the mask back keeps the stored secret, and a new value replaces it', function () {
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_password' => 'stored-smtp-password',
    ], $this->companyId);

    $payload = [
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_username' => 'mailer',
        'mail_timeout' => 30,
        'mail_password' => MailConfigurationService::SECRET_MASK,
        'from_name' => 'Company Mailer',
        'from_mail' => 'company@example.com',
    ];

    postJson('/api/v1/company/mail/company-config', $payload)->assertOk();

    expect(CompanySetting::getSetting('company_mail_password', $this->companyId))
        ->toBe('stored-smtp-password');

    postJson('/api/v1/company/mail/company-config', ['mail_password' => 'rotated-password'] + $payload)->assertOk();

    expect(CompanySetting::getSetting('company_mail_password', $this->companyId))
        ->toBe('rotated-password');
});

test('a sendmail command stored in the settings is never run', function () {
    $configured = config('mail.mailers.sendmail.path');

    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'sendmail',
        'company_mail_sendmail_path' => 'touch /tmp/pwned; /usr/sbin/sendmail -bs -i',
    ], $this->companyId);
    Setting::setSettings([
        'mail_driver' => 'sendmail',
        'mail_sendmail_path' => 'touch /tmp/pwned-global; /usr/sbin/sendmail -bs -i',
    ]);

    app(MailConfigurationService::class)->applyCompanyConfig($this->companyId);
    expect(config('mail.mailers.sendmail.path'))->toBe($configured);

    app(MailConfigurationService::class)->applyGlobalConfig();
    expect(config('mail.mailers.sendmail.path'))->toBe($configured);
});

test('a sendmail path is neither saved nor sent back', function () {
    postJson('/api/v1/company/mail/company-config', [
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'sendmail',
        'mail_sendmail_path' => 'id > /tmp/pwned',
        'from_name' => 'Company Mailer',
        'from_mail' => 'company@example.com',
    ])->assertOk();

    expect(CompanySetting::getSetting('company_mail_sendmail_path', $this->companyId))->toBeNull();

    getJson('/api/v1/company/mail/company-config')
        ->assertOk()
        ->assertJsonMissingPath('mail_sendmail_path');
});

test('upgrading deletes the sendmail commands already stored', function () {
    CompanySetting::setSettings(['company_mail_sendmail_path' => 'id > /tmp/pwned'], $this->companyId);
    Setting::setSettings(['mail_sendmail_path' => 'id > /tmp/pwned']);

    (require database_path('migrations/2026_09_23_110000_forget_stored_sendmail_paths.php'))->up();

    expect(CompanySetting::getSetting('company_mail_sendmail_path', $this->companyId))->toBeNull()
        ->and(Setting::getSetting('mail_sendmail_path'))->toBeNull();
});
