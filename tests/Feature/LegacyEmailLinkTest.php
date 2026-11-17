<?php

use App\Http\Controllers\V1\Customer\LegacyEmailLinkController;
use App\Mail\LegacyEmailLinkMail;
use App\Models\CompanySetting;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\PublicToken;
use Hashids\Hashids;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * The link an email sent before 2.4.4 carried: a Hashids token derived from
 * the email log's id, built the way SendInvoiceMail used to build it.
 */
function oldEmailLink(EmailLog $log, string $document = 'invoices'): string
{
    $config = config('hashids.connections.'.EmailLog::class);
    $token = (new Hashids(EmailLog::class.config('app.key'), $config['length'], $config['alphabet']))->encode($log->id);

    return "/customer/{$document}/view/{$token}";
}

/** An email sent before the upgrade, whose token the upgrade has since re-issued. */
function emailSentBeforeUpgrade(Invoice|Estimate|Payment $document, array $attributes = []): EmailLog
{
    $log = EmailLog::query()->create([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Your document',
        'body' => 'Please find it attached.',
        'mailable_type' => $document::class,
        'mailable_id' => $document->id,
        'token' => PublicToken::make(),
        ...$attributes,
    ]);

    Setting::setSetting(LegacyEmailLinkController::CUTOFF_SETTING, $log->id);

    return $log;
}

function expireLinksAfter(int $days, int $companyId): void
{
    CompanySetting::setSettings([
        'automatically_expire_public_links' => 'YES',
        'link_expiry_days' => $days,
    ], $companyId);
}

/** The URL the last LegacyEmailLinkMail carried. */
function resentUrl(): string
{
    $url = null;

    Mail::assertSent(LegacyEmailLinkMail::class, function (LegacyEmailLinkMail $mail) use (&$url): bool {
        $url = $mail->url;

        return true;
    });

    return $url;
}

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Mail::fake();
});

test('an old link offers a new link and opens nothing', function () {
    $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_SENT]);
    $log = emailSentBeforeUpgrade($invoice);

    get(oldEmailLink($log))
        ->assertOk()
        ->assertSee('Email me a new link')
        ->assertDontSee($invoice->invoice_number)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    // Opening the page is not a view of the invoice.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_SENT);
    Mail::assertNothingSent();
});

test('asking for a new link emails the current link to the address the email went to', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create(), [
        'to' => 'customer@example.com',
        'cc' => 'accountant@example.com',
    ]);

    post(oldEmailLink($log))->assertOk()->assertSee('Check your inbox');

    Mail::assertSent(LegacyEmailLinkMail::class, function (LegacyEmailLinkMail $mail) use ($log): bool {
        return $mail->hasTo('customer@example.com')
            && ! $mail->hasTo('accountant@example.com')
            && ! $mail->hasCc('accountant@example.com')
            && $mail->url === route('invoice', ['email_log' => $log->token]);
    });
});

test('the new link opens the invoice', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());

    post(oldEmailLink($log));

    get(resentUrl())->assertOk();
});

test('estimate and payment emails get their own new links', function (string $class, string $document, string $route) {
    $log = emailSentBeforeUpgrade($class::factory()->create());

    post(oldEmailLink($log, $document))->assertOk();

    expect(resentUrl())->toBe(route($route, ['email_log' => $log->token]));
})->with([
    'estimate' => [Estimate::class, 'estimates', 'estimate'],
    'payment' => [Payment::class, 'payments', 'payment'],
]);

test('an old link that has expired says so and sends nothing', function () {
    $invoice = Invoice::factory()->create();
    expireLinksAfter(7, $invoice->company_id);
    $log = emailSentBeforeUpgrade($invoice, ['created_at' => now()->subDays(8)]);

    get(oldEmailLink($log))->assertForbidden()->assertSee('This link has expired');
    post(oldEmailLink($log))->assertForbidden();

    Mail::assertNothingSent();
});

test('an old link still inside its expiry works', function () {
    $invoice = Invoice::factory()->create();
    expireLinksAfter(7, $invoice->company_id);
    $log = emailSentBeforeUpgrade($invoice, ['created_at' => now()->subDays(6)]);

    post(oldEmailLink($log))->assertOk();

    Mail::assertSent(LegacyEmailLinkMail::class);
});

test('an old link answers only on the route for its kind of document', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());

    get(oldEmailLink($log, 'payments'))->assertNotFound();
    get(oldEmailLink($log, 'estimates'))->assertNotFound();
    post(oldEmailLink($log, 'payments'))->assertNotFound();

    Mail::assertNothingSent();
});

test('emails sent after the cutoff have no old link', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());
    Setting::setSetting(LegacyEmailLinkController::CUTOFF_SETTING, $log->id - 1);

    get(oldEmailLink($log))->assertNotFound();
    post(oldEmailLink($log))->assertNotFound();

    Mail::assertNothingSent();
});

test('a token Hashids never produced is not found', function () {
    emailSentBeforeUpgrade(Invoice::factory()->create());

    get('/customer/invoices/view/notAnOldLinkToken123')->assertNotFound();
    get('/customer/invoices/view/'.str_repeat('B', 20))->assertNotFound();
});

test('current links still reach the document', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());

    get(route('invoice', ['email_log' => $log->token]))->assertOk();
    get('/customer/invoices/'.$log->token)->assertOk();
});

test('an email without a valid address gets no new link', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create(), ['to' => 'not an address']);

    post(oldEmailLink($log))->assertUnprocessable()->assertSee('contact');

    Mail::assertNothingSent();
});

test('new links for one email are limited', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());

    foreach (range(1, 3) as $attempt) {
        post(oldEmailLink($log))->assertOk();
    }

    post(oldEmailLink($log))->assertTooManyRequests();

    Mail::assertSent(LegacyEmailLinkMail::class, 3);
});

test('the email names the document and the last day its link works', function () {
    $invoice = Invoice::factory()->create();
    expireLinksAfter(7, $invoice->company_id);
    CompanySetting::setSettings(['carbon_date_format' => 'd.m.Y'], $invoice->company_id);
    $log = emailSentBeforeUpgrade($invoice, ['created_at' => now()->subDays(2)]);
    $url = route('invoice', ['email_log' => $log->token]);

    (new LegacyEmailLinkMail($log, $url))
        ->assertHasSubject("New link to invoice {$invoice->invoice_number} from {$invoice->company->name}")
        ->assertSeeInHtml($url)
        ->assertSeeInHtml($invoice->invoice_number)
        ->assertSeeInHtml('This link works until '.now()->addDays(5)->format('d.m.Y').'.');
});

test('the page uses the company language, and English where it has no translation yet', function () {
    $invoice = Invoice::factory()->create();
    CompanySetting::setSettings(['language' => 'de'], $invoice->company_id);
    $log = emailSentBeforeUpgrade($invoice);

    get(oldEmailLink($log))
        ->assertOk()
        ->assertSee('<html lang="de">', false)
        ->assertSee('Email me a new link');
});

test('the email uses the company language, and English where it has no translation yet', function () {
    $invoice = Invoice::factory()->create();
    CompanySetting::setSettings(['language' => 'de'], $invoice->company_id);
    $log = emailSentBeforeUpgrade($invoice);
    app()->setLocale('en');

    $germanButton = __('mail_view_invoice', [], 'de');
    expect($germanButton)->not->toBe(__('mail_view_invoice', [], 'en'));

    (new LegacyEmailLinkMail($log, route('invoice', ['email_log' => $log->token])))
        ->assertSeeInHtml($germanButton)
        ->assertSeeInHtml('You asked for a new link to invoice');
});

test('the upgrade records the newest email as the last one with an old link', function () {
    $log = emailSentBeforeUpgrade(Invoice::factory()->create());
    Setting::setSetting(LegacyEmailLinkController::CUTOFF_SETTING, 0);

    (require database_path('migrations/2026_11_17_000000_record_legacy_email_link_cutoff.php'))->up();

    expect((int) Setting::getSetting(LegacyEmailLinkController::CUTOFF_SETTING))->toBe($log->id);
});
