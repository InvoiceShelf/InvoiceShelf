<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $this->company = Company::query()->first();
    $this->withHeaders(['company' => $this->company->id]);
});

function actAsCompanyOwner(Company $company): User
{
    $owner = User::factory()->create(['role' => 'admin']);
    $owner->companies()->attach($company->id);
    $company->update(['owner_id' => $owner->id]);

    Sanctum::actingAs($owner, ['*']);

    return $owner;
}

function smtpPayload(array $overrides = []): array
{
    return array_merge([
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'from_name' => 'Company Mailer',
        'from_mail' => 'company@example.com',
    ], $overrides);
}

test('a company owner cannot point smtp at a private address', function (string $host) {
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_host' => $host]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mail_host']);
})->with(['127.0.0.1', '10.0.0.5', '169.254.169.254', '[::1]']);

test('a company owner cannot hide a private host in the smtp url', function () {
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_url' => 'smtp://user:pass@127.0.0.1:25']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mail_url']);
});

test('a company owner may only use the official mailgun endpoints', function () {
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', [
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'mailgun',
        'mail_mailgun_domain' => 'mg.example.com',
        'mail_mailgun_secret' => 'key-123',
        'mail_mailgun_endpoint' => '10.0.0.5',
        'from_name' => 'Company Mailer',
        'from_mail' => 'company@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors(['mail_mailgun_endpoint']);
});

test('a company owner can still use a public smtp host', function () {
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_host' => '93.184.215.14']))
        ->assertOk();
});

test('the super administrator may use a relay on the private network', function () {
    Sanctum::actingAs(User::query()->find(1), ['*']);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_host' => '127.0.0.1']))
        ->assertOk();
});

test('a test mail refuses a private host stored before the check existed', function () {
    actAsCompanyOwner($this->company);

    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_host' => '127.0.0.1',
        'company_mail_port' => '25',
    ], $this->company->id);

    postJson('/api/v1/company/mail/company-test', [
        'to' => 'someone@example.com',
        'subject' => 'Hello',
        'message' => 'Test',
    ])->assertUnprocessable()->assertJsonValidationErrors(['mail_host']);
});

test('a private relay the operator names in MAIL_ALLOWED_PRIVATE_HOSTS is allowed', function () {
    config(['network.allowed_private_hosts.mail' => ['192.168.1.10', 'mail.lan']]);
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_host' => '192.168.1.10']))
        ->assertOk();

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_url' => 'smtp://user:pass@192.168.1.10:25']))
        ->assertOk();
});

test('naming one private relay leaves every other private address blocked', function () {
    config(['network.allowed_private_hosts.mail' => ['192.168.1.10']]);
    actAsCompanyOwner($this->company);

    postJson('/api/v1/company/mail/company-config', smtpPayload(['mail_host' => '10.0.0.5']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mail_host']);
});

test('a test mail goes out through a named private relay', function () {
    Mail::fake();
    config(['network.allowed_private_hosts.mail' => ['192.168.1.10']]);
    actAsCompanyOwner($this->company);

    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_host' => '192.168.1.10',
        'company_mail_port' => '25',
    ], $this->company->id);

    postJson('/api/v1/company/mail/company-test', [
        'to' => 'someone@example.com',
        'subject' => 'Hello',
        'message' => 'Test',
    ])->assertOk();
});
