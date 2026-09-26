<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Notifications\CustomerMailResetPasswordNotification;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Contacts\CreateCustomerTool;
use App\Platform\Mcp\Tools\Contacts\SearchCustomersTool;
use App\Platform\Operations\Managed\ManagedMode;
use App\Platform\Operations\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\McpTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->owner = User::findOrFail(1);
    $this->company = $this->owner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->owner, ['*']);
    config(['managed.enabled' => true, 'managed.read_only' => true]);
});

test('all business mutation methods are refused before their controllers run', function (string $method, string $path) {
    $this->json($method, $path)->assertForbidden()->assertJsonPath('error', 'read_only');
})->with([
    ['POST', '/api/v1/customers'], ['POST', '/api/v1/invoices'], ['POST', '/api/v1/payments'],
    ['POST', '/api/v1/company/settings'], ['PUT', '/api/v1/me/settings'],
    ['POST', '/api/v1/modules/install'], ['POST', '/api/v1/members'],
    ['POST', '/api/v1/companies'], ['POST', '/api/v1/invoices/delete'],
]);

test('reads and both bootstrap surfaces remain available', function () {
    $this->getJson('/api/v1/customers')->assertOk();
    $this->getJson('/api/v1/invoices')->assertOk();
    $this->getJson('/api/v1/app/client-manifest')->assertOk()->assertJsonPath('managed.read_only', true);
    config(['managed.billing_url' => 'https://billing.example.test/account']);
    expect(ManagedMode::clientState())->toMatchArray(['read_only' => true, 'billing_url' => 'https://billing.example.test/account']);
    $this->followingRedirects()->get('/login')->assertOk()->assertSee('read_only', false);
});

test('own password changes work but cannot smuggle in identity or custom field changes', function () {
    $payload = ['name' => $this->owner->name, 'email' => $this->owner->email, 'password' => 'new-password-123', 'confirm_password' => 'new-password-123'];
    $this->putJson('/api/v1/me', $payload)->assertOk();
    expect(Hash::check('new-password-123', $this->owner->fresh()->password))->toBeTrue();
    $this->putJson('/api/v1/me', [...$payload, 'name' => 'Changed'])->assertForbidden()->assertJsonPath('error', 'read_only');
    $this->putJson('/api/v1/me', [...$payload, 'customFields' => []])->assertForbidden();
});

test('password recovery remains available for staff and portal users and business mail is suppressed', function () {
    config(['mail.default' => 'array']);
    Mail::purge();
    $transport = Mail::mailer()->getSymfonyTransport();
    Mail::raw('An invoice must not be sent.', fn ($message) => $message->to('client@example.test')->subject('Invoice'));
    expect($transport->messages())->toHaveCount(0);
    $this->owner->notify(new MailResetPasswordNotification('fixture-token'));
    expect($transport->messages())->toHaveCount(1);
    $customer = Customer::factory()->create(['company_id' => $this->company->id, 'email' => 'portal@example.test']);
    $customer->notify(new CustomerMailResetPasswordNotification('fixture-customer-token'));
    expect($transport->messages())->toHaveCount(2);
});

test('the scheduler skips module callbacks as well as built in tasks', function () {
    $ran = false;
    app(Schedule::class)->call(function () use (&$ran) {
        $ran = true;
    })->everyMinute();
    $this->artisan('schedule:run')->assertSuccessful();
    expect($ran)->toBeFalse();
});

test('catch up does not update its marker even when forced', function () {
    Setting::setSetting('daily_sweeps_ran_on', '2000-01-01');
    $this->artisan('invoiceshelf:catch-up', ['--force' => true])->assertSuccessful();
    expect(Setting::getSetting('daily_sweeps_ran_on'))->toBe('2000-01-01');
});

test('public document viewing cannot change its status or trigger viewed mail', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->company->id, 'status' => Invoice::STATUS_SENT, 'viewed' => false]);
    $log = EmailLog::query()->create([
        'from' => 'owner@example.test', 'to' => 'client@example.test', 'subject' => 'Invoice', 'body' => 'Your invoice is ready to view.',
        'mailable_type' => $invoice->getMorphClass(), 'mailable_id' => $invoice->id,
        'company_id' => $this->company->id, 'token' => Str::random(64),
    ]);
    $this->getJson('/customer/invoices/'.$log->token)->assertOk();
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_SENT)->and((bool) $invoice->fresh()->viewed)->toBeFalse();
});

test('the flag affects only managed installs and can be cleared without changing data', function () {
    config(['managed.enabled' => false]);
    expect(ManagedMode::readOnly())->toBeFalse();
    $this->postJson('/api/v1/customers', [])->assertUnprocessable();
    config(['managed.enabled' => true, 'managed.read_only' => false]);
    $this->postJson('/api/v1/customers', [])->assertUnprocessable();
});

test('portal users can change their own password without changing their profile', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id, 'email' => 'portal@example.test', 'password' => Hash::make('old-password-123')]);
    $this->actingAs($customer, 'customer');
    $url = '/api/v1/'.$this->company->slug.'/customer/profile';
    $this->postJson($url, ['password' => 'new-password-123'])->assertOk();
    expect(Hash::check('new-password-123', $customer->fresh()->password))->toBeTrue();
    $this->postJson($url, ['password' => 'new-password-123', 'email' => 'someone-else@example.test'])->assertForbidden()->assertJsonPath('error', 'read_only');
});

test('MCP keeps read tools and refuses write tools even on a write connection', function () {
    $context = McpTesting::actAs($this->owner, $this->company->id);
    expect(app(CreateCustomerTool::class)->shouldRegister($context))->toBeFalse();
    $before = Customer::query()->count();
    InvoiceShelfServer::actingAs($this->owner)
        ->tool(CreateCustomerTool::class, ['name' => 'Must not be created'])->assertHasErrors();
    expect(Customer::query()->count())->toBe($before);
    $result = McpTesting::call($this->owner, SearchCustomersTool::class);
    expect($result)->not->toBeEmpty();
});

test('the signed in token may be revoked while read only', function () {
    $this->postJson('/api/v1/auth/logout')->assertSuccessful();
});
