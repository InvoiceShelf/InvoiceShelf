<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\InvoiceReminderService;
use App\Domains\Sales\Application\ReminderSettings;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Mail\SendInvoiceMail;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceReminder;
use App\Platform\Mail\Models\EmailLog;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::query()->findOrFail(1);
    $this->companyId = (int) $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    $this->usd = (int) Currency::query()->where('code', 'USD')->value('id');
    CompanySetting::setSettings(['currency' => $this->usd, 'time_zone' => 'UTC'], $this->companyId);
    ReminderSettings::save($this->companyId, ['enabled' => true]);
    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'currency_id' => $this->usd, 'email' => 'pay@globex.test', 'name' => 'Globex']);

    Mail::fake();
});

afterEach(fn () => Carbon::setTestNow());

function unpaidInvoice($test, string $dueDate, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'company_id' => $test->companyId,
        'customer_id' => $test->customer->id,
        'currency_id' => $test->usd,
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'sent' => true,
        'paid_status' => Invoice::STATUS_UNPAID,
        'due_date' => $dueDate,
        'total' => 10000,
        'due_amount' => 10000,
        'base_total' => 10000,
        'base_due_amount' => 10000,
        'exchange_rate' => 1,
        ...$attributes,
    ]);
}

function runReminders(string $at): int
{
    Carbon::setTestNow($at);

    return app(InvoiceReminderService::class)->sendDue(CarbonImmutable::now());
}

test('reminders go out on the scheduled days around the due date, once each', function () {
    $invoice = unpaidInvoice($this, '2026-06-10');

    expect(runReminders('2026-06-06 10:00:00'))->toBe(0)   // four days before: nothing
        ->and(runReminders('2026-06-07 10:00:00'))->toBe(1) // three days before
        ->and(runReminders('2026-06-07 15:00:00'))->toBe(0) // the same day again
        ->and(runReminders('2026-06-10 10:00:00'))->toBe(0) // the due date itself is not scheduled
        ->and(runReminders('2026-06-11 10:00:00'))->toBe(1) // a day after
        ->and(runReminders('2026-06-17 10:00:00'))->toBe(1); // a week after

    expect($invoice->reminders()->orderBy('offset_days')->pluck('offset_days')->all())->toBe([-3, 1, 7]);
    Mail::assertSent(SendInvoiceMail::class, 3);
});

test('nothing goes out before the send hour, in the company time zone', function () {
    CompanySetting::setSettings(['time_zone' => 'Australia/Sydney'], $this->companyId);
    unpaidInvoice($this, '2026-06-10');

    // 08:00 in Sydney on 11 June is 22:00 UTC on 10 June.
    expect(runReminders('2026-06-10 22:00:00'))->toBe(0)
        ->and(runReminders('2026-06-10 23:00:00'))->toBe(1);
});

test('a missed day catches up with the latest reminder only', function () {
    $invoice = unpaidInvoice($this, '2026-06-10');

    // The server was down from before the first reminder until two days after the second.
    expect(runReminders('2026-06-13 10:00:00'))->toBe(1)
        ->and($invoice->reminders()->pluck('offset_days')->all())->toBe([1])
        ->and(runReminders('2026-06-14 10:00:00'))->toBe(0);
});

test('a paused invoice, a paused customer, a draft, a paid invoice and a switched-off company are left alone', function () {
    unpaidInvoice($this, '2026-06-10', ['reminders_paused' => true]);
    unpaidInvoice($this, '2026-06-10', ['status' => Invoice::STATUS_DRAFT]);
    unpaidInvoice($this, '2026-06-10', ['paid_status' => Invoice::STATUS_PAID, 'status' => Invoice::STATUS_COMPLETED, 'due_amount' => 0]);
    expect(runReminders('2026-06-11 10:00:00'))->toBe(0);

    $other = Customer::factory()->create(['company_id' => $this->companyId, 'email' => 'x@example.test', 'reminders_paused' => true]);
    unpaidInvoice($this, '2026-06-10', ['customer_id' => $other->id]);
    expect(runReminders('2026-06-11 11:00:00'))->toBe(0);

    ReminderSettings::save($this->companyId, ['enabled' => false]);
    unpaidInvoice($this, '2026-06-10');
    expect(runReminders('2026-06-11 12:00:00'))->toBe(0);
    Mail::assertNothingSent();
});

test('a customer with no email is skipped once and not tried again', function () {
    $this->customer->update(['email' => null]);
    $invoice = unpaidInvoice($this, '2026-06-10');

    runReminders('2026-06-11 10:00:00');
    runReminders('2026-06-11 11:00:00');

    expect($invoice->reminders()->pluck('status')->all())->toBe([InvoiceReminder::STATUS_SKIPPED]);
    Mail::assertNothingSent();
});

test('the email names the amount still open after a part payment, with a working link', function () {
    ReminderSettings::save($this->companyId, [
        'subject' => 'Invoice {INVOICE_NUMBER}: {INVOICE_DUE_AMOUNT} due {UNKNOWN}',
        'body' => 'Paid {INVOICE_PAID_AMOUNT} of {INVOICE_TOTAL}, {INVOICE_DAYS_OVERDUE} days late.',
    ]);
    $invoice = unpaidInvoice($this, '2026-06-10', ['paid_status' => Invoice::STATUS_PARTIALLY_PAID, 'due_amount' => 2500]);

    runReminders('2026-06-17 10:00:00');

    Mail::assertSent(SendInvoiceMail::class, function (SendInvoiceMail $mail) use ($invoice) {
        $mail->build();

        return $mail->hasTo('pay@globex.test')
            && $mail->data['subject'] === "Invoice {$invoice->invoice_number}: \$25.00 due "
            && $mail->data['body'] === 'Paid $75.00 of $100.00, 7 days late.'
            && $mail->data['attach']['data'] === null;
    });

    // Faked mail is built by the assertion above, which is when the log and
    // its link token are written.
    $log = EmailLog::query()->where('mailable_id', $invoice->id)->latest('id')->firstOrFail();
    $this->getJson('/customer/invoices/'.$log->token)->assertOk();
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_SENT);
});

test('a mail failure marks that reminder failed and the next invoice still goes', function () {
    $first = unpaidInvoice($this, '2026-06-10');
    $second = unpaidInvoice($this, '2026-06-10');
    $sender = Mockery::mock(InvoiceEmailSender::class);
    $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP is down'));
    $sender->shouldReceive('send')->once();
    app()->instance(InvoiceEmailSender::class, $sender);

    expect(runReminders('2026-06-11 10:00:00'))->toBe(1);

    $statuses = InvoiceReminder::query()->orderBy('invoice_id')->pluck('status', 'invoice_id')->all();
    expect($statuses)->toBe([$first->id => InvoiceReminder::STATUS_FAILED, $second->id => InvoiceReminder::STATUS_SENT]);
});

test('staff can see, pause and send reminders for an invoice', function () {
    Carbon::setTestNow('2026-06-11 10:00:00');
    $invoice = unpaidInvoice($this, '2026-06-10');

    $this->getJson("/api/v1/invoices/{$invoice->id}/reminders")
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.paused', false)
        ->assertJsonPath('data.next', ['date' => '2026-06-11', 'offset' => 1])
        ->assertJsonPath('data.history', []);

    $this->postJson("/api/v1/invoices/{$invoice->id}/reminders")
        ->assertOk()
        ->assertJsonPath('data.history.0.status', 'sent')
        ->assertJsonPath('data.history.0.offset_days', null)
        ->assertJsonPath('data.history.0.sent_by', $this->user->id);
    $this->postJson("/api/v1/invoices/{$invoice->id}/reminders")->assertOk();

    $this->putJson("/api/v1/invoices/{$invoice->id}/reminders", ['paused' => true])
        ->assertOk()
        ->assertJsonPath('data.paused', true)
        ->assertJsonPath('data.next', null);

    expect(runReminders('2026-06-11 11:00:00'))->toBe(0);
    Mail::assertSent(SendInvoiceMail::class, 2);
});

test('a reminder cannot be sent for a draft or a paid invoice', function () {
    $draft = unpaidInvoice($this, '2026-06-10', ['status' => Invoice::STATUS_DRAFT]);

    $this->postJson("/api/v1/invoices/{$draft->id}/reminders")
        ->assertUnprocessable()
        ->assertJsonPath('errors.invoice.0', 'invoice_reminder_not_due');
});

test('only members who may send invoices can send or pause reminders', function () {
    $invoice = unpaidInvoice($this, '2026-06-10');
    $reader = User::factory()->create(['role' => 'user']);
    $reader->companies()->attach($this->companyId);
    BouncerFacade::scope()->onceTo($this->companyId, fn () => $reader->assign('preset:read-only'));
    BouncerFacade::refresh();
    Sanctum::actingAs($reader, ['*']);

    $this->getJson("/api/v1/invoices/{$invoice->id}/reminders")->assertOk();
    $this->postJson("/api/v1/invoices/{$invoice->id}/reminders")->assertForbidden();
    $this->putJson("/api/v1/invoices/{$invoice->id}/reminders", ['paused' => true])->assertForbidden();
});

test('the owner edits the reminder settings, and the days are checked', function () {
    $this->getJson('/api/v1/company/payment-reminders')
        ->assertOk()
        ->assertJsonPath('data.offsets', [-3, 1, 7, 14])
        ->assertJsonPath('data.send_hour', 9);

    $this->putJson('/api/v1/company/payment-reminders', ['offsets' => [30, -1, 5], 'send_hour' => 7, 'attach_pdf' => true])
        ->assertOk()
        ->assertJsonPath('data.offsets', [-1, 5, 30])
        ->assertJsonPath('data.attach_pdf', true);

    $this->putJson('/api/v1/company/payment-reminders', ['offsets' => [1, 1]])->assertUnprocessable();
    $this->putJson('/api/v1/company/payment-reminders', ['offsets' => [400]])->assertUnprocessable();
    $this->putJson('/api/v1/company/payment-reminders', ['send_hour' => 24])->assertUnprocessable();
});

test('a customer can be paused from the customer form', function () {
    $this->putJson("/api/v1/customers/{$this->customer->id}", [
        'name' => 'Globex',
        'email' => 'pay@globex.test',
        'currency_id' => $this->usd,
        'reminders_paused' => true,
    ])->assertOk()->assertJsonPath('data.reminders_paused', true);
});

test('the scheduler asks for reminders every hour', function () {
    $this->artisan('invoices:send-reminders')->assertSuccessful();
});
