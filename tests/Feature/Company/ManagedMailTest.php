<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * On a managed install the provider sets the server's mail transport in the
 * environment, a company may bring only its own SMTP server, and mail sent
 * through the platform goes out from the platform address with the company's
 * chosen address as Reply-To.
 */

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);
});

function companySmtp(array $overrides = []): array
{
    return array_merge([
        'use_custom_mail_config' => 'YES',
        'mail_driver' => 'smtp',
        'mail_host' => '93.184.215.14',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'from_name' => 'Acme',
        'from_mail' => 'billing@acme.test',
    ], $overrides);
}

test('the environment decides the server transport on a managed install', function () {
    config(['managed.enabled' => true, 'mail.default' => 'smtp']);
    Setting::setSettings(['mail_driver' => 'sendmail']);

    app(MailConfigurationService::class)->applyGlobalConfig();

    expect(config('mail.default'))->toBe('smtp');
});

test('a company on a managed install may only bring its own smtp server', function (array $overrides, string $field) {
    config(['managed.enabled' => true]);

    postJson('/api/v1/company/mail/company-config', companySmtp($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'another driver' => [['mail_driver' => 'postmark', 'mail_postmark_token' => 'x'], 'mail_driver'],
    'port 25' => [['mail_port' => 25], 'mail_port'],
    'no encryption' => [['mail_encryption' => 'none'], 'mail_encryption'],
    'a dsn' => [['mail_url' => 'smtp://user:pass@93.184.215.14:587'], 'mail_url'],
    'a private host, even for the super admin' => [['mail_host' => '10.0.0.5'], 'mail_host'],
]);

test('the managed port and encryption errors name what is accepted', function () {
    config(['managed.enabled' => true]);

    postJson('/api/v1/company/mail/company-config', companySmtp(['mail_port' => 25, 'mail_encryption' => 'none']))
        ->assertJsonPath('errors.mail_port.0', 'The mail port must be 465, 587 or 2525.')
        ->assertJsonPath('errors.mail_encryption.0', 'The mail encryption must be TLS or SSL.');
});

test('a company on a managed install can save its own smtp server', function () {
    config(['managed.enabled' => true]);

    postJson('/api/v1/company/mail/company-config', companySmtp())->assertOk();
});

test('the company mail page lists the drivers it may use', function () {
    getJson('/api/v1/company/mail/drivers')->assertOk()->assertJsonFragment(['postmark']);

    config(['managed.enabled' => true]);

    getJson('/api/v1/company/mail/drivers')->assertOk()->assertExactJson(['smtp']);
});

test('a company owner who is not the super admin can load the driver list', function () {
    $owner = User::factory()->create(['role' => 'admin']);
    $owner->companies()->attach($this->companyId);
    Company::find($this->companyId)->update(['owner_id' => $owner->id]);
    Sanctum::actingAs($owner, ['*']);

    getJson('/api/v1/company/mail/drivers')->assertOk();
});

test('mail through the platform goes out from the platform address with the chosen address as reply-to', function () {
    Mail::fake();
    config(['managed.enabled' => true, 'mail.from.address' => 'acme@mail.invhost.com']);
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);

    postJson("api/v1/invoices/{$invoice->id}/send", [
        'from' => 'billing@acme.test',
        'to' => 'client@example.com',
        'subject' => 'Invoice',
        'body' => 'Attached.',
    ])->assertOk();

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->build()->hasFrom('acme@mail.invhost.com')
        && $mail->hasReplyTo('billing@acme.test'));
});

test('a company using its own smtp server keeps its own sender', function () {
    Mail::fake();
    config(['managed.enabled' => true, 'mail.from.address' => 'acme@mail.invhost.com']);
    CompanySetting::setSettings([
        'use_custom_mail_config' => 'YES',
        'company_mail_driver' => 'smtp',
        'company_mail_host' => '93.184.215.14',
        'company_mail_port' => '587',
        'company_mail_encryption' => 'tls',
    ], $this->companyId);
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);

    postJson("api/v1/invoices/{$invoice->id}/send", [
        'from' => 'billing@acme.test',
        'to' => 'client@example.com',
        'subject' => 'Invoice',
        'body' => 'Attached.',
    ])->assertOk();

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->build()->hasFrom('billing@acme.test')
        && ! $mail->hasReplyTo('billing@acme.test'));
});

test('an ordinary install sends from the chosen address as before', function () {
    Mail::fake();
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId]);

    postJson("api/v1/invoices/{$invoice->id}/send", [
        'from' => 'billing@acme.test',
        'to' => 'client@example.com',
        'subject' => 'Invoice',
        'body' => 'Attached.',
    ])->assertOk();

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->build()->hasFrom('billing@acme.test'));
});
