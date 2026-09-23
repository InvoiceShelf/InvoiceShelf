<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $this->companyId = User::query()->find(1)->companies()->first()->id;

    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_host' => 'smtp.example.com',
        'company_mail_password' => 'stored-smtp-password',
        'company_mail_postmark_token' => 'stored-postmark-token',
        'module.ai-assistant.api_key' => 'stored-module-key',
    ], $this->companyId);

    $member = User::factory()->create(['email' => 'member@example.com', 'role' => 'user']);
    $member->companies()->attach($this->companyId);

    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($member, ['*']);
});

test('the bootstrap leaves the mail transport and module settings out', function () {
    $settings = getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertDontSee('stored-smtp-password')
        ->assertDontSee('stored-postmark-token')
        ->assertDontSee('stored-module-key')
        ->json('current_company_settings');

    expect($settings)->toHaveKey('currency')
        ->not->toHaveKey('company_mail_password')
        ->not->toHaveKey('company_mail_host')
        ->not->toHaveKey('use_custom_mail_config')
        ->not->toHaveKey('module.ai-assistant.api_key');
});

test('a member cannot read the mail transport or module settings by name', function () {
    $settings = getJson('/api/v1/company/settings?'.http_build_query(['settings' => [
        'currency',
        'company_mail_password',
        'company_mail_postmark_token',
        'module.ai-assistant.api_key',
    ]]))
        ->assertOk()
        ->assertDontSee('stored-smtp-password')
        ->json();

    expect(array_keys($settings))->toBe(['currency']);
});

test('the owner cannot write the mail transport or module settings through the generic endpoint', function () {
    Sanctum::actingAs(User::query()->find(1), ['*']);

    postJson('/api/v1/company/settings', ['settings' => [
        'language' => 'de',
        'company_mail_host' => 'attacker.example.com',
        'module.ai-assistant.api_key' => 'replaced',
    ]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings']);

    expect(CompanySetting::getSetting('company_mail_host', $this->companyId))->toBe('smtp.example.com')
        ->and(CompanySetting::getSetting('module.ai-assistant.api_key', $this->companyId))->toBe('stored-module-key');

    postJson('/api/v1/company/settings', ['settings' => ['language' => 'de']])->assertOk();

    expect(CompanySetting::getSetting('language', $this->companyId))->toBe('de');
});

test('settings that are not a map are refused', function () {
    Sanctum::actingAs(User::query()->find(1), ['*']);

    postJson('/api/v1/company/settings', ['settings' => 'language'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings']);
});
