<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\InvoicePaymentLinks;
use App\Domains\Sales\Application\InvoiceReminderService;
use App\Domains\Sales\Application\ReminderSettings;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use App\Platform\Mail\Contracts\EmailLogWriter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * A payment link is a URL the company already has from its payment provider,
 * printed on the invoice (with a QR code), put in the invoice email and behind
 * the Pay button of the public page and the customer portal. InvoiceShelf only
 * points at it; the payment is still recorded by hand.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::query()->findOrFail(1);
    $this->companyId = (int) $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    $this->usd = (int) Currency::query()->where('code', 'USD')->value('id');
    CompanySetting::setSettings(['currency' => $this->usd, 'time_zone' => 'UTC'], $this->companyId);
    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd, 'email' => 'pay@globex.test']);
});

afterEach(fn () => Carbon::setTestNow());

function invoiceWithBalance($test, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'company_id' => $test->companyId,
        'customer_id' => $test->customer->id,
        'currency_id' => $test->usd,
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'invoice_number' => 'INV 7/2026',
        'total' => 125050,
        'due_amount' => 125050,
        'exchange_rate' => 1,
        'template_name' => 'invoice1',
        ...$attributes,
    ]);
}

function setCompanyPaymentLink($test, ?string $link): void
{
    CompanySetting::setSettings([InvoicePaymentLinks::SETTING => $link], $test->companyId);
}

const PAYMENT_TEMPLATE_LINK = 'https://pay.example.com/acme?ref={INVOICE_NUMBER}&amount={INVOICE_DUE_AMOUNT}&currency={INVOICE_CURRENCY}';
const PAYMENT_FILLED_LINK = 'https://pay.example.com/acme?ref=INV%207%2F2026&amount=1250.50&currency=USD';

test('the company link names the invoice, what is left to pay and the currency', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);

    expect(app(InvoicePaymentLinks::class)->forInvoice(invoiceWithBalance($this)))->toBe(PAYMENT_FILLED_LINK);
});

test('the amount follows the currency precision', function () {
    $yen = Currency::query()->where('code', 'JPY')->firstOrFail();
    setCompanyPaymentLink($this, 'https://pay.example.com/{INVOICE_DUE_AMOUNT}/{INVOICE_CURRENCY}');

    $invoice = invoiceWithBalance($this, ['currency_id' => $yen->id, 'due_amount' => 125000]);

    expect(app(InvoicePaymentLinks::class)->forInvoice($invoice))
        ->toBe('https://pay.example.com/'.number_format(1250, $yen->precision, '.', '').'/JPY');
});

test('an invoice link wins over the company link', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);

    $invoice = invoiceWithBalance($this, ['payment_link' => 'https://payoneer.example/request/abc123']);

    expect(app(InvoicePaymentLinks::class)->forInvoice($invoice))->toBe('https://payoneer.example/request/abc123');
});

test('there is no link without one, once paid, or on a credit note', function () {
    $links = app(InvoicePaymentLinks::class);

    expect($links->forInvoice(invoiceWithBalance($this)))->toBeNull();

    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);

    expect($links->forInvoice(invoiceWithBalance($this, ['invoice_number' => 'INV-PAID', 'due_amount' => 0, 'paid_status' => Invoice::STATUS_PAID])))->toBeNull()
        ->and($links->forInvoice(invoiceWithBalance($this, ['invoice_number' => 'CN-1', 'type' => Invoice::TYPE_CREDIT_NOTE])))->toBeNull();
});

test('a stored link that is not a web address is never handed out', function () {
    setCompanyPaymentLink($this, 'javascript:alert(1)');

    $links = app(InvoicePaymentLinks::class);

    expect($links->forInvoice(invoiceWithBalance($this)))->toBeNull()
        ->and($links->forInvoice(invoiceWithBalance($this, ['invoice_number' => 'INV-2', 'payment_link' => 'data:text/html,hi'])))->toBeNull()
        ->and($links->forInvoice(invoiceWithBalance($this, ['invoice_number' => 'INV-3', 'payment_link' => 'https://pay.example.com/{CUSTOMER_EMAIL}'])))->toBeNull();
});

test('the settings take a link with placeholders and refuse anything else', function () {
    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::SETTING => PAYMENT_TEMPLATE_LINK]])->assertOk();
    expect(CompanySetting::getSetting(InvoicePaymentLinks::SETTING, $this->companyId))->toBe(PAYMENT_TEMPLATE_LINK);

    foreach (['javascript:alert(1)', 'pay.example.com/acme', 'https://pay.example.com/{CUSTOMER_EMAIL}', 'https://pay.example.com/'.str_repeat('a', 2048)] as $refused) {
        postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::SETTING => $refused]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.'.InvoicePaymentLinks::SETTING);
    }

    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::SETTING => '']])->assertOk();
    expect(app(InvoicePaymentLinks::class)->forInvoice(invoiceWithBalance($this)))->toBeNull();

    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::QR_SETTING => 'NO']])->assertOk();
    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::QR_SETTING => 'MAYBE']])
        ->assertJsonValidationErrors('settings.'.InvoicePaymentLinks::QR_SETTING);
    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::QR_SETTING => null]])
        ->assertJsonValidationErrors('settings.'.InvoicePaymentLinks::QR_SETTING);
});

test('an unknown placeholder is named in the error', function () {
    postJson('api/v1/company/settings', ['settings' => [InvoicePaymentLinks::SETTING => 'https://pay.example.com/{CUSTOMER_EMAIL}']])
        ->assertJsonPath('errors', fn (array $errors) => str_contains(implode(' ', $errors['settings.'.InvoicePaymentLinks::SETTING]), '{CUSTOMER_EMAIL}'));
});

test('an invoice keeps a link of its own and refuses one that is not a web address', function () {
    $payload = fn (array $overrides) => Invoice::factory()->raw([
        'customer_id' => $this->customer->id,
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
        ...$overrides,
    ]);

    $saved = $payload(['payment_link' => 'https://payoneer.example/request/abc123']);
    postJson('api/v1/invoices', $saved)
        ->assertOk()
        ->assertJsonPath('data.payment_link', 'https://payoneer.example/request/abc123');

    postJson('api/v1/invoices', $payload(['payment_link' => 'ftp://files.example.com/pay']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_link');
});

test('the PDF prints the link with a QR code of it', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);

    foreach (['invoice1', 'invoice2', 'invoice3'] as $template) {
        $invoice = invoiceWithBalance($this, ['invoice_number' => 'INV 7/2026', 'template_name' => $template]);
        $html = get("/invoices/pdf/{$invoice->unique_hash}?preview")->assertOk()->getContent();

        expect($html)->toContain('Pay Online')
            ->toContain('href="'.e(PAYMENT_FILLED_LINK).'"')
            ->toContain('data:image/svg+xml;base64,');

        $invoice->forceDelete();
    }
});

test('the QR code can be switched off and the link stays', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);
    CompanySetting::setSettings([InvoicePaymentLinks::QR_SETTING => 'NO'], $this->companyId);

    $html = get('/invoices/pdf/'.invoiceWithBalance($this)->unique_hash.'?preview')->assertOk()->getContent();

    expect($html)->toContain('href="'.e(PAYMENT_FILLED_LINK).'"')
        ->not->toContain('data:image/svg+xml;base64,');
});

test('a paid invoice prints no payment block', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);

    $invoice = invoiceWithBalance($this, ['due_amount' => 0, 'paid_status' => Invoice::STATUS_PAID]);
    $html = get("/invoices/pdf/{$invoice->unique_hash}?preview")->assertOk()->getContent();

    expect($html)->not->toContain('Pay Online')->not->toContain('pay.example.com');
});

test('the invoice email has a Pay Online button', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);
    $invoice = invoiceWithBalance($this);

    $html = getJson("api/v1/invoices/{$invoice->id}/send/preview?".http_build_query([
        'from' => 'billing@example.com',
        'to' => 'pay@globex.test',
        'subject' => 'Invoice',
        'body' => 'Here it is.',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Pay Online')->toContain('href="'.e(PAYMENT_FILLED_LINK).'"');
});

test('a payment reminder carries the link too', function () {
    Mail::fake();
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);
    ReminderSettings::save($this->companyId, ['enabled' => true]);
    invoiceWithBalance($this, ['due_date' => '2026-06-10', 'sent' => true]);

    Carbon::setTestNow('2026-06-11 10:00:00');
    expect(app(InvoiceReminderService::class)->sendDue(CarbonImmutable::now()))->toBe(1);

    Mail::assertSent(SendInvoiceMail::class, fn (SendInvoiceMail $mail) => $mail->data['payment_link'] === PAYMENT_FILLED_LINK);
});

test('the customer gets the filled link on the public page and in the portal', function () {
    setCompanyPaymentLink($this, PAYMENT_TEMPLATE_LINK);
    $invoice = invoiceWithBalance($this);

    $token = app(EmailLogWriter::class)->record($invoice, [
        'from' => 'billing@example.com',
        'to' => 'pay@globex.test',
        'subject' => 'Your invoice',
        'body' => 'Here it is.',
    ]);

    getJson("/customer/invoices/{$token}")->assertOk()->assertJsonPath('data.payment_link_url', PAYMENT_FILLED_LINK);

    Sanctum::actingAs($this->customer, ['*'], 'customer');

    getJson("/api/v1/{$this->customer->company->slug}/customer/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_link_url', PAYMENT_FILLED_LINK)
        ->assertJsonMissingPath('data.payment_link');
});

test('a copy of an invoice does not keep its link', function () {
    $invoice = invoiceWithBalance($this, ['payment_link' => 'https://payoneer.example/request/abc123']);

    $copy = postJson("api/v1/invoices/{$invoice->id}/clone")->assertCreated()->json('data');

    expect(Invoice::query()->findOrFail($copy['id'])->payment_link)->toBeNull();
});
