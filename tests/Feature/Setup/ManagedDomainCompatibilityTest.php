<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Accounts\Notifications\MailResetPasswordNotification;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Notifications\CustomerMailResetPasswordNotification;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Models\EmailLog;
use App\Support\Urls\CustomerUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\Support\McpTesting;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->withoutVite();
    $this->owner = User::findOrFail(1);
    $this->company = $this->owner->companies()->first();
    config(['managed.enabled' => true, 'managed.read_only' => false, 'app.url' => 'https://invoices.example.com', 'session.domain' => null, 'session.secure' => true,
        'session.cookie' => '__Host-invoiceshelf_session', 'sanctum.stateful' => ['invoices.example.com', 'luna.invoiceshelf.app'], 'invoiceshelf.customer_portal.url' => null]);
});

test('custom and recovery origins both keep staff and portal entrypoints', function (string $host) {
    expect(CustomerUrl::separate())->toBeFalse();
    $this->followingRedirects()->get('https://'.$host.'/login')->assertOk()->assertSee('window.InvoiceShelf.start()', false);
    $this->followingRedirects()->get('https://'.$host.'/'.$this->company->slug.'/customer/login')->assertOk()->assertSee('window.InvoiceShelf.start()', false);
    expect(parse_url($this->app['request']->url(), PHP_URL_HOST))->toBe($host);
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);

test('oauth discovery and challenges stay on the origin chosen by the client', function (string $host) {
    McpTesting::enable();
    $base = 'https://'.$host;
    $this->getJson($base.'/.well-known/oauth-protected-resource/mcp')->assertOk()
        ->assertJsonPath('resource', $base.'/mcp')->assertJsonPath('authorization_servers.0', $base);
    $this->getJson($base.'/.well-known/oauth-authorization-server')->assertOk()
        ->assertJsonPath('issuer', $base)->assertJsonPath('token_endpoint', $base.'/oauth/token');
    $response = $this->postJson($base.'/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))->toContain($base.'/.well-known/oauth-protected-resource/mcp');
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);

test('a device can sign in on a custom host and revoke the same token through recovery', function () {
    $this->owner->forceFill(['password' => 'domain-test-password'])->save();
    $response = $this->postJson('https://invoices.example.com/api/v1/auth/login', ['username' => $this->owner->email, 'password' => 'domain-test-password', 'device_name' => 'existing-device'])->assertOk();
    $token = $response->json('token');
    $this->withHeaders(['Authorization' => 'Bearer '.$token, 'company' => $this->company->id]);
    $this->getJson('https://luna.invoiceshelf.app/api/v1/auth/check')->assertOk();
    $this->postJson('https://luna.invoiceshelf.app/api/v1/auth/logout')->assertSuccessful();
    app('auth')->forgetGuards();
    $this->getJson('https://invoices.example.com/api/v1/auth/check')->assertUnauthorized();
});

test('sessions and csrf cookies are secure and limited to each host', function (string $host) {
    $this->withHeaders(['Origin' => 'https://'.$host, 'Referer' => 'https://'.$host.'/login']);
    $response = $this->get('https://'.$host.'/sanctum/csrf-cookie')->assertNoContent();
    foreach ($response->headers->getCookies() as $cookie) {
        expect($cookie->isSecure())->toBeTrue()->and($cookie->getDomain())->toBeNull()->and($cookie->getPath())->toBe('/');
    }
    $request = Request::create('https://'.$host.'/api/v1/bootstrap', 'GET', server: ['HTTP_ORIGIN' => 'https://'.$host]);
    expect(EnsureFrontendRequestsAreStateful::fromFrontend($request))->toBeTrue();
    $request->headers->set('Origin', 'https://foreign.example.com');
    expect(EnsureFrontendRequestsAreStateful::fromFrontend($request))->toBeFalse();
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);

test('portal login and readable documents work on either origin without copying records', function (string $host) {
    $customer = Customer::factory()->create(['company_id' => $this->company->id, 'password' => 'secret123', 'enable_portal' => true]);
    $this->postJson('https://'.$host.'/'.$this->company->slug.'/customer/login', ['email' => $customer->email, 'password' => 'secret123'])->assertOk();
    $this->assertAuthenticatedAs($customer, 'customer');
    $invoice = Invoice::factory()->create(['company_id' => $this->company->id, 'customer_id' => $customer->id]);
    $log = EmailLog::query()->create(['from' => 'owner@example.com', 'to' => $customer->email, 'subject' => 'Invoice', 'body' => 'Your invoice', 'mailable_type' => $invoice->getMorphClass(), 'mailable_id' => $invoice->id, 'company_id' => $this->company->id, 'token' => Str::random(64)]);
    $this->getJson('https://invoices.example.com/customer/invoices/'.$log->token)->assertOk();
    $this->getJson('https://luna.invoiceshelf.app/customer/invoices/'.$log->token)->assertOk();
    expect(Invoice::whereKey($invoice->id)->count())->toBe(1);
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);

test('personal tokens and read-only rules apply on every app alias', function (string $host) {
    $token = $this->owner->createToken('existing-device')->plainTextToken;
    $this->withHeaders(['Authorization' => 'Bearer '.$token, 'company' => $this->company->id]);
    $this->getJson('https://'.$host.'/api/v1/customers')->assertOk();
    config(['managed.read_only' => true]);
    $this->postJson('https://'.$host.'/api/v1/customers', ['name' => 'Forbidden'])->assertForbidden()->assertJsonPath('error', 'read_only');
    $this->getJson('https://'.$host.'/api/v1/invoices')->assertOk();
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);

test('password links follow the request origin so the recovery address stays useful', function (string $host) {
    $this->get('https://'.$host.'/up')->assertOk();
    $staff = (new MailResetPasswordNotification('staff-token'))->toMail($this->owner);
    expect(parse_url($staff->actionUrl, PHP_URL_HOST))->toBe($host);
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $portal = (new CustomerMailResetPasswordNotification('portal-token'))->toMail($customer);
    expect(parse_url($portal->actionUrl, PHP_URL_HOST))->toBe($host);
})->with(['invoices.example.com', 'luna.invoiceshelf.app']);
