<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Notifications\CustomerMailResetPasswordNotification;
use App\Domains\Receivables\Mail\SendPaymentMail;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use App\Support\Urls\CustomerUrl;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

/**
 * The customer portal on a host of its own (CUSTOMER_PORTAL_URL). Every link
 * sent to a customer points there, the portal host serves the portal and
 * public documents and nothing else, and the app host sends customers there.
 */
const PORTAL_HOST_URL = 'https://clients-acme.example.test';
const STAFF_HOST_URL = 'https://acme.example.test';

beforeEach(function () {
    // One company, until a test sends a document.
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Setting::setSetting('profile_complete', InstallationState::COMPLETED);

    config(['app.url' => STAFF_HOST_URL]);
    $this->withoutVite();
    $this->slug = Company::query()->firstOrFail()->slug;
});

/**
 * Whether a request got past the host check. Pages behind the install gate
 * redirect to the installer in tests (its schema check is cached from before
 * the migrations ran), so anything but the host check's 404 or its move to
 * the portal counts.
 */
function passesHostCheck(TestResponse $response): void
{
    expect($response->status())->not->toBeIn([301, 404]);
}

/**
 * Send a payment receipt and return the link it carried and its token.
 *
 * @return array{0: string, 1: string}
 */
function sendPaymentLink(): array
{
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $mail = new SendPaymentMail([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Payment receipt',
        'body' => 'Thanks for your payment.',
        'payment' => Payment::factory()->create()->toArray(),
        'attach' => ['data' => null],
    ]);
    $mail->build();

    return [$mail->data['url'], EmailLog::query()->latest('id')->firstOrFail()->token];
}

test('without a portal host every link and page stays where it was', function () {
    [$url, $token] = sendPaymentLink();

    expect(CustomerUrl::separate())->toBeFalse()
        ->and($url)->toBe(route('payment', ['email_log' => $token]));

    passesHostCheck(get(STAFF_HOST_URL.'/login'));
    passesHostCheck(get(STAFF_HOST_URL.'/customer/payments/view/'.$token));
});

test('links sent to customers point at the portal host', function () {
    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);

    [$url, $token] = sendPaymentLink();
    expect($url)->toBe(PORTAL_HOST_URL.'/customer/payments/view/'.$token);

    $customer = Customer::factory()->create(['company_id' => Company::query()->firstOrFail()->id]);
    $mail = (new CustomerMailResetPasswordNotification('reset-token'))->toMail($customer);
    expect($mail->actionUrl)->toBe(PORTAL_HOST_URL."/{$this->slug}/customer/reset/password/reset-token");
});

test('the portal host serves the portal and public documents only', function () {
    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);
    [, $token] = sendPaymentLink();

    get(PORTAL_HOST_URL.'/up')->assertOk();
    passesHostCheck(get(PORTAL_HOST_URL."/{$this->slug}/customer/login"));
    getJson(PORTAL_HOST_URL."/api/v1/{$this->slug}/customer/bootstrap")->assertUnauthorized();
    passesHostCheck(get(PORTAL_HOST_URL.'/customer/payments/view/'.$token));
    getJson(PORTAL_HOST_URL.'/customer/payments/'.$token)->assertOk();
    get(PORTAL_HOST_URL.'/sanctum/csrf-cookie')->assertNoContent();
    getJson(PORTAL_HOST_URL.'/api/v1/app/version')->assertOk();

    get(PORTAL_HOST_URL.'/login')->assertNotFound();
    post(PORTAL_HOST_URL.'/login')->assertNotFound();
    get(PORTAL_HOST_URL.'/admin/dashboard')->assertNotFound();
    get(PORTAL_HOST_URL.'/installation')->assertNotFound();
    getJson(PORTAL_HOST_URL.'/api/v1/invoices')->assertNotFound();
    getJson(PORTAL_HOST_URL.'/api/v1/bootstrap')->assertNotFound();
    get(PORTAL_HOST_URL.'/mcp')->assertNotFound();
});

test('the portal host opens the portal of the only company', function () {
    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);

    get(PORTAL_HOST_URL.'/')->assertRedirect(PORTAL_HOST_URL."/{$this->slug}/customer/login");

    Company::factory()->create();

    get(PORTAL_HOST_URL.'/')->assertNotFound();
});

test('the app host sends customers to the portal host', function () {
    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);
    [, $token] = sendPaymentLink();

    get(STAFF_HOST_URL.'/customer/payments/view/'.$token)
        ->assertStatus(301)
        ->assertRedirect(PORTAL_HOST_URL.'/customer/payments/view/'.$token);
    get(STAFF_HOST_URL."/{$this->slug}/customer/invoices?page=2")
        ->assertStatus(301)
        ->assertRedirect(PORTAL_HOST_URL."/{$this->slug}/customer/invoices?page=2");

    passesHostCheck(get(STAFF_HOST_URL.'/login'));
});

test('several hosts can serve the portal', function () {
    config([
        'invoiceshelf.customer_portal.url' => PORTAL_HOST_URL,
        'invoiceshelf.customer_portal.hosts' => 'clients-acme.example.test, billing.acme.example',
    ]);

    get('https://billing.acme.example/up')->assertOk();
    get('https://billing.acme.example/login')->assertNotFound();
});

test('a portal url on the app host changes nothing', function () {
    config(['invoiceshelf.customer_portal.url' => STAFF_HOST_URL.'/']);

    expect(CustomerUrl::separate())->toBeFalse();
    passesHostCheck(get(STAFF_HOST_URL.'/login'));
});

test('the portal host keeps its session', function () {
    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);

    CustomerUrl::trustPortalHosts();

    expect(config('sanctum.stateful'))->toContain('clients-acme.example.test');
});

test('the staff app learns the portal address', function () {
    expect(getJson('/api/v1/app/client-manifest')->json('customer_portal_url'))->toBeNull();

    config(['invoiceshelf.customer_portal.url' => PORTAL_HOST_URL]);

    expect(getJson(STAFF_HOST_URL.'/api/v1/app/client-manifest')->json('customer_portal_url'))->toBe(PORTAL_HOST_URL);
});
