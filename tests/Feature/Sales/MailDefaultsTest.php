<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Application\Composition\PaymentMailDefaults;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\Composition\EstimateMailDefaults;
use App\Domains\Sales\Application\Composition\InvoiceMailDefaults;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->companyId = User::find(1)->companies()->first()->id;
    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'email' => 'ap@acme.test']);

    config(['mail.from.address' => 'billing@invoiceshelf.test']);
    CompanySetting::setSettings([
        'invoice_mail_body' => 'Invoice {INVOICE_NUMBER} is attached.',
        'estimate_mail_body' => 'Estimate {ESTIMATE_NUMBER} is attached.',
        'payment_mail_body' => 'Thank you for payment {PAYMENT_NUMBER}.',
    ], $this->companyId);
});

test('an invoice is sent the way the send dialog would prefill it', function () {
    $invoice = Invoice::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id]);

    expect(app(InvoiceMailDefaults::class)->for($invoice, 'en'))->toBe([
        'from' => 'billing@invoiceshelf.test',
        'to' => 'ap@acme.test',
        'cc' => null,
        'bcc' => null,
        'subject' => 'New Invoice',
        'body' => 'Invoice {INVOICE_NUMBER} is attached.',
    ]);
});

test('an estimate is sent the way the send dialog would prefill it', function () {
    $estimate = Estimate::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id]);

    expect(app(EstimateMailDefaults::class)->for($estimate, 'de'))->toMatchArray([
        'to' => 'ap@acme.test',
        'subject' => 'Neues Angebot',
        'body' => 'Estimate {ESTIMATE_NUMBER} is attached.',
    ]);
});

test('a payment receipt is sent the way the send dialog would prefill it', function () {
    $payment = Payment::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id]);

    expect(app(PaymentMailDefaults::class)->for($payment, 'pt_BR'))->toMatchArray([
        'from' => 'billing@invoiceshelf.test',
        'to' => 'ap@acme.test',
        'subject' => 'Novo Pagamento',
        'body' => 'Thank you for payment {PAYMENT_NUMBER}.',
    ]);
});
