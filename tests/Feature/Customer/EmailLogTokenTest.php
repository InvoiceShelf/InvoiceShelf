<?php

namespace Tests\Feature\Customer;

use App\Models\CompanySetting;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\get;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('email-log token enforces the mailable type (no cross-type disclosure)', function () {
    $payment = Payment::factory()->create();

    // Token issued for a Payment must not resolve a document on the invoice
    // or estimate routes, even if the numeric id collides.
    $emailLog = EmailLog::factory()->create([
        'mailable_type' => Payment::class,
        'mailable_id' => $payment->id,
        'token' => 'tok-type-confusion',
    ]);

    get('/customer/invoices/'.$emailLog->token)->assertNotFound();
    get('/customer/estimates/'.$emailLog->token)->assertNotFound();
});

test('json invoice endpoint enforces link expiry', function () {
    $invoice = Invoice::factory()->create();

    CompanySetting::setSettings([
        'automatically_expire_public_links' => 'YES',
        'link_expiry_days' => '1',
    ], $invoice->company_id);

    $emailLog = EmailLog::factory()->create([
        'mailable_type' => Invoice::class,
        'mailable_id' => $invoice->id,
        'token' => 'tok-expired',
        'created_at' => now()->subDays(5),
    ]);

    get('/customer/invoices/'.$emailLog->token)->assertForbidden();
});

test('json invoice endpoint returns the invoice for a valid token', function () {
    $invoice = Invoice::factory()->create();

    $emailLog = EmailLog::factory()->create([
        'mailable_type' => Invoice::class,
        'mailable_id' => $invoice->id,
        'token' => 'tok-valid',
    ]);

    get('/customer/invoices/'.$emailLog->token)->assertOk();
});
