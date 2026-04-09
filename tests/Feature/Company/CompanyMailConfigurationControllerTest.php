<?php

use App\Models\CompanySetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\CompanyMailConfigService;
use App\Services\MailConfigurationService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
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

    CompanyMailConfigService::apply($this->companyId);

    expect(config('mail.default'))->toBe('postmark');
    expect(config('services.postmark.token'))->toBe('runtime-postmark-token');
    expect(config('mail.mailers.postmark.message_stream_id'))->toBe('outbound');
    expect(config('mail.from.name'))->toBe('Runtime Mailer');
    expect(config('mail.from.address'))->toBe('runtime@example.com');
});
