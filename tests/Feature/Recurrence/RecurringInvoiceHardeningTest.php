<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Mail\RecurringInvoiceFailedMail;
use App\Domains\Sales\Mail\RecurringInvoiceGeneratedMail;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\artisan;

/**
 * Recurring invoices hold the same line as recurring bills and expenses:
 * failures are kept and told, runs are logged once, limits settle, and the
 * schedule can be paused and resumed.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->user, ['*']);

    Carbon::setTestNow('2026-06-15 12:00:00');
    CompanySetting::setSettings(['time_zone' => 'Europe/Skopje'], $this->companyId);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A daily schedule (midnight in Skopje) whose run for 15 June has fallen due.
 */
function hardenedSchedule($test, array $attributes = []): RecurringInvoice
{
    $customer = Customer::factory()->create(['company_id' => $test->companyId]);

    return RecurringInvoice::factory()->create([
        'company_id' => $test->companyId,
        'customer_id' => $customer->id,
        'currency_id' => $customer->currency_id,
        'creator_id' => $test->user->id,
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'limit_by' => RecurringInvoice::NONE,
        'starts_at' => '2026-06-01 00:00:00',
        'next_invoice_at' => '2026-06-14 22:00:00',
        ...$attributes,
    ]);
}

function hardenedPayload(RecurringInvoice $schedule, array $overrides = []): array
{
    return [
        ...$schedule->only(['customer_id', 'discount_type', 'discount', 'discount_val', 'template_name', 'exchange_rate', 'currency_id']),
        'starts_at' => '2026-06-01',
        'frequency' => $schedule->frequency,
        'limit_by' => 'NONE',
        'send_automatically' => false,
        'items' => [['name' => 'Retainer', 'quantity' => 1, 'price' => 1000, 'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1000]],
        'taxes' => [],
        'sub_total' => 1000,
        'total' => 1000,
        'tax' => 0,
        ...$overrides,
    ];
}

function invoicesOf(RecurringInvoice $schedule)
{
    return Invoice::query()->where('recurring_invoice_id', $schedule->id);
}

test('a mail server that is down keeps the invoice as a draft and says so on the schedule', function () {
    app()->instance(InvoiceEmailSender::class, new class implements InvoiceEmailSender
    {
        public function send(array $data, bool $creditNote): void
        {
            throw new RuntimeException('SMTP is down');
        }
    });
    $schedule = hardenedSchedule($this, ['send_automatically' => true]);
    $other = hardenedSchedule($this);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->sole()->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($schedule->fresh()->last_error)->toBe('recurring_invoice_send_failed')
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-06-15 22:00:00')
        ->and(invoicesOf($other)->count())->toBe(1);
});

test('editing a schedule keeps whoever set it up as its creator', function () {
    $founder = User::factory()->create();
    $schedule = hardenedSchedule($this, ['creator_id' => $founder->id]);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule))->assertSuccessful();

    expect($schedule->fresh()->creator_id)->toBe($founder->id);
});

test('raising the count of a completed schedule restarts it from today without making up runs', function () {
    $schedule = hardenedSchedule($this, [
        'status' => RecurringInvoice::COMPLETED,
        'limit_by' => RecurringInvoice::COUNT,
        'limit_count' => 1,
        'next_invoice_at' => '2026-05-01 22:00:00',
    ]);
    $schedule->occurrences()->create(['company_id' => $this->companyId, 'scheduled_for' => '2026-05-01', 'scheduled_at' => '2026-04-30 22:00:00', 'record_type' => 'invoice', 'record_id' => 0]);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, ['limit_by' => 'COUNT', 'limit_count' => 3]))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'ACTIVE');

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->pluck('invoice_date')->all())->toBe(['2026-06-15']);
});

test('a schedule cannot be set to completed by hand', function () {
    $schedule = hardenedSchedule($this);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, ['status' => 'COMPLETED']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('deleting a generated invoice does not free a place under the count limit', function () {
    $schedule = hardenedSchedule($this, ['limit_by' => RecurringInvoice::COUNT, 'limit_count' => 5]);
    artisan('recurring-invoices:generate')->assertSuccessful();

    invoicesOf($schedule)->sole()->delete();
    // An invoice from before runs were logged counts as a run of its own.
    Invoice::factory()->create(['company_id' => $this->companyId, 'customer_id' => $schedule->customer_id, 'recurring_invoice_id' => $schedule->id]);

    expect($schedule->fresh()->generatedCount())->toBe(2);
});

test('a run already logged for its moment is not made again', function () {
    $schedule = hardenedSchedule($this);
    artisan('recurring-invoices:generate')->assertSuccessful();

    // Put back by hand, as a restored backup or a time zone change might.
    $schedule->fresh()->forceFill(['next_invoice_at' => '2026-06-14 22:00:00'])->save();
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->count())->toBe(1)
        ->and($schedule->occurrences()->count())->toBe(1);
});

test('the schedule form refuses a customer, a count or an end date it cannot use', function () {
    $schedule = hardenedSchedule($this);
    $stranger = Customer::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, ['customer_id' => $stranger->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, ['limit_by' => 'COUNT', 'limit_count' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors('limit_count');
    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, ['limit_by' => 'DATE', 'limit_date' => '2026-05-01']))
        ->assertUnprocessable()->assertJsonValidationErrors('limit_date');
});

test('a single schedule is not deleted through an unhandled route', function () {
    $schedule = hardenedSchedule($this);

    $status = $this->deleteJson('/api/v1/recurring-invoices/'.$schedule->id)->status();

    expect($status)->toBeIn([404, 405])
        ->and($schedule->fresh())->not->toBeNull();
});

test('a paused schedule makes nothing, and resuming keeps today but skips the paused runs', function () {
    $schedule = hardenedSchedule($this, ['next_invoice_at' => '2026-06-10 22:00:00']);

    $this->postJson('/api/v1/recurring-invoices/'.$schedule->id.'/actions', ['action' => 'pause'])
        ->assertSuccessful()->assertJsonPath('data.status', 'ON_HOLD');
    $this->postJson('/api/v1/recurring-invoices/'.$schedule->id.'/actions', ['action' => 'pause'])
        ->assertUnprocessable()->assertJsonPath('errors.recurring_invoice.0', 'recurring_invoice_not_active');
    artisan('recurring-invoices:generate')->assertSuccessful();
    expect(invoicesOf($schedule)->count())->toBe(0);

    $this->postJson('/api/v1/recurring-invoices/'.$schedule->id.'/actions', ['action' => 'resume'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'ACTIVE')
        ->assertJsonPath('data.next_invoice_at', '2026-06-14 22:00:00');
    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->pluck('invoice_date')->all())->toBe(['2026-06-15']);
});

test('a template that no longer passes fails with a reason, emails once, and a save lets it run again', function () {
    Mail::fake();
    $schedule = hardenedSchedule($this, ['notify_creator' => true]);
    $field = CustomField::factory()->create(['company_id' => $this->companyId, 'model_type' => 'Invoice', 'type' => 'Input', 'label' => 'PO number', 'name' => 'po_number', 'is_required' => true, 'order' => 0]);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->count())->toBe(0)
        ->and($schedule->fresh()->last_error)->toBe('recurring_invoice_custom_field_required');
    Mail::assertSent(RecurringInvoiceFailedMail::class, fn ($mail) => $mail->hasTo($this->user->email));

    Carbon::setTestNow('2026-06-15 14:00:00');
    artisan('recurring-invoices:generate')->assertSuccessful();
    Mail::assertSent(RecurringInvoiceFailedMail::class, 1);

    $this->putJson('/api/v1/recurring-invoices/'.$schedule->id, hardenedPayload($schedule, [
        'notify_creator' => true,
        'customFields' => [['id' => $field->id, 'value' => 'PO-42']],
    ]))->assertSuccessful()->assertJsonPath('data.last_error', null);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->count())->toBe(1)
        ->and($schedule->fresh()->last_error)->toBeNull();
    Mail::assertSent(RecurringInvoiceGeneratedMail::class, fn ($mail) => $mail->hasTo($this->user->email));
});

test('nobody is emailed when the schedule does not ask for it, or its creator has left', function () {
    Mail::fake();
    hardenedSchedule($this);
    $leaver = User::factory()->create();
    $leaver->companies()->attach($this->companyId);
    hardenedSchedule($this, ['notify_creator' => true, 'creator_id' => $leaver->id]);
    $leaver->companies()->detach($this->companyId);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(Invoice::query()->whereNotNull('recurring_invoice_id')->count())->toBe(2);
    Mail::assertNothingSent();
});

test('an invoice on the 31st is made in the months that have one', function () {
    Carbon::setTestNow('2026-01-31 12:00:00');
    $schedule = hardenedSchedule($this, ['frequency' => '0 0 31 * *', 'next_invoice_at' => '2026-01-30 23:00:00']);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->sole()->invoice_date)->toBe('2026-01-31')
        // 31 March, midnight in Skopje (summer time by then).
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-03-30 22:00:00');
});

test('a time lost when the clocks go forward still makes that day\'s invoice', function () {
    // 02:30 does not exist in Skopje on 29 March 2026; the run is at 03:30.
    Carbon::setTestNow('2026-03-29 01:31:00');
    $schedule = hardenedSchedule($this, ['frequency' => '30 2 * * *', 'next_invoice_at' => '2026-03-29 01:30:00']);

    artisan('recurring-invoices:generate')->assertSuccessful();

    expect(invoicesOf($schedule)->sole()->invoice_date)->toBe('2026-03-29')
        ->and($schedule->fresh()->next_invoice_at)->toBe('2026-03-30 00:30:00');
});
