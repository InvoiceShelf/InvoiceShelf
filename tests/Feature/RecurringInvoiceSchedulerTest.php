<?php

use App\Http\Resources\RecurringInvoiceResource;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Services\Document\InvoiceService;
use App\Services\Document\RecurringInvoiceScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config()->set('app.timezone', 'UTC');

    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->schedulerCompany = User::findOrFail(1)->companies()->firstOrFail();

    CompanySetting::setSettings([
        'time_zone' => 'UTC',
        'invoice_due_date_days' => 7,
        'invoice_email_attachment' => 'NO',
        'invoice_mail_body' => 'Invoice {INVOICE_NUMBER}',
        'invoice_number_format' => '{{DATE_FORMAT:Ymd}}{{DELIMITER:-}}{{SEQUENCE:3}}',
    ], $this->schedulerCompany->id);
});

afterEach(function () {
    Carbon::setTestNow();
});

function createSchedulerTemplate(int $companyId, array $attributes = []): RecurringInvoice
{
    $customerId = $attributes['customer_id'] ?? Customer::factory()->create([
        'company_id' => $companyId,
    ])->id;

    return RecurringInvoice::factory()->create(array_merge([
        'company_id' => $companyId,
        'creator_id' => User::findOrFail(1)->id,
        'customer_id' => $customerId,
        'starts_at' => '2026-01-01 00:00:00',
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 0 * * *',
        'next_invoice_at' => '2026-08-01 00:00:00',
        'limit_by' => RecurringInvoice::NONE,
        'limit_count' => null,
        'limit_date' => null,
        'send_automatically' => false,
        'exchange_rate' => 1,
    ], $attributes));
}

test('the scheduler registers one recurring invoice dispatcher', function () {
    $routes = file_get_contents(base_path('routes/console.php'));

    expect($routes)
        ->toContain("Schedule::command('generate:recurring-invoices')")
        ->toContain('->everyMinute()')
        ->toContain('->withoutOverlapping(60)')
        ->not->toContain('RecurringInvoice::where')
        ->not->toContain('Schedule::call');
});

test('recurrence calculations use the company timezone across daylight saving time', function () {
    $company = Company::create(['name' => 'Timezone test']);
    CompanySetting::setSettings(['time_zone' => 'America/New_York'], $company->id);

    $schedule = app(RecurringInvoiceScheduleService::class);
    $next = $schedule->nextOccurrence(
        '0 9 * * *',
        Carbon::parse('2026-03-07 09:00:00', 'America/New_York'),
        'America/New_York'
    );

    expect($next->format('Y-m-d H:i:s P'))->toBe('2026-03-08 09:00:00 -04:00')
        ->and($schedule->toStored($next))->toBe('2026-03-08 13:00:00');
});

test('stored occurrences convert between different application and company timezones', function () {
    config()->set('app.timezone', 'Europe/Skopje');
    CompanySetting::setSettings(['time_zone' => 'America/New_York'], $this->schedulerCompany->id);
    $schedule = app(RecurringInvoiceScheduleService::class);
    $occurrence = Carbon::parse('2026-08-02 09:00:00', 'America/New_York');

    $stored = $schedule->toStored($occurrence);

    expect($stored)->toBe('2026-08-02 15:00:00')
        ->and($schedule->fromStored($stored, $this->schedulerCompany->id)->format('Y-m-d H:i:s P'))
        ->toBe('2026-08-02 09:00:00 -04:00');
});

test('recurring invoice resources expose the next occurrence in company time', function () {
    CompanySetting::setSettings([
        'time_zone' => 'America/New_York',
        'carbon_date_format' => 'Y-m-d',
    ], $this->schedulerCompany->id);
    $recurringInvoice = createSchedulerTemplate($this->schedulerCompany->id, [
        'next_invoice_at' => '2026-03-08 13:00:00',
    ]);

    $resource = (new RecurringInvoiceResource($recurringInvoice))->resolve();

    expect($resource['next_invoice_at'])->toBe('2026-03-08 09:00:00')
        ->and($resource['formatted_next_invoice_at'])->toBe('2026-03-08');
});

test('a future start matching the cron is retained as the first occurrence', function () {
    $company = Company::create(['name' => 'Future start test']);
    CompanySetting::setSettings(['time_zone' => 'UTC'], $company->id);

    $occurrence = app(RecurringInvoiceScheduleService::class)->firstFutureOccurrence(
        '0 9 * * *',
        '2030-01-01 09:00:00',
        $company->id,
        Carbon::parse('2029-12-31 09:00:00', 'UTC')
    );

    expect($occurrence->format('Y-m-d H:i:s'))->toBe('2030-01-01 09:00:00');
});

test('a future start after the cron minute advances to the next occurrence', function () {
    $occurrence = app(RecurringInvoiceScheduleService::class)->firstFutureOccurrence(
        '0 9 * * *',
        '2030-01-01 09:00:30',
        $this->schedulerCompany->id,
        Carbon::parse('2029-12-31 09:00:00', 'UTC')
    );

    expect($occurrence->format('Y-m-d H:i:s'))->toBe('2030-01-02 09:00:00');
});

test('past starts are scheduled at the next future occurrence', function () {
    $next = app(RecurringInvoiceScheduleService::class)->firstFutureOccurrence(
        '0 0 * * *',
        '2026-07-01 00:00:00',
        $this->schedulerCompany->id,
        Carbon::parse('2026-08-02 12:00:00', 'UTC')
    );

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-08-03 00:00:00');
});

test('catch-up invoices use each scheduled date for document dates and number placeholders', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'UTC'));
    $recurringInvoice = createSchedulerTemplate($this->schedulerCompany->id);

    Artisan::call('generate:recurring-invoices');

    $invoices = Invoice::query()
        ->where('recurring_invoice_id', $recurringInvoice->id)
        ->orderBy('invoice_date')
        ->get();

    expect($invoices)->toHaveCount(3)
        ->and($invoices->map(fn (Invoice $invoice) => Carbon::parse($invoice->invoice_date)->toDateString())->all())
        ->toBe(['2026-08-01', '2026-08-02', '2026-08-03'])
        ->and($invoices->map(fn (Invoice $invoice) => Carbon::parse($invoice->due_date)->toDateString())->all())
        ->toBe(['2026-08-08', '2026-08-09', '2026-08-10'])
        ->and($invoices->pluck('invoice_number')->all())
        ->toBe(['20260801-001', '20260802-002', '20260803-003'])
        ->and($recurringInvoice->fresh()->next_invoice_at)
        ->toBe('2026-08-04 00:00:00');
});

test('catch-up work is fair and limited to ten invoices per template', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 00:30:00', 'UTC'));
    $first = createSchedulerTemplate($this->schedulerCompany->id, [
        'frequency' => '* * * * *',
        'next_invoice_at' => '2026-08-01 00:00:00',
    ]);
    $second = createSchedulerTemplate($this->schedulerCompany->id, [
        'frequency' => '* * * * *',
        'next_invoice_at' => '2026-08-01 00:00:00',
    ]);

    Artisan::call('generate:recurring-invoices');

    expect($first->invoices()->count())->toBe(10)
        ->and($second->invoices()->count())->toBe(10)
        ->and($first->fresh()->next_invoice_at)->toBe('2026-08-01 00:10:00')
        ->and($second->fresh()->next_invoice_at)->toBe('2026-08-01 00:10:00');
});

test('a dispatcher invocation generates at most one hundred invoices', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
    $customer = Customer::factory()->create(['company_id' => $this->schedulerCompany->id]);

    $recurringInvoices = RecurringInvoice::factory()->count(101)->create([
        'company_id' => $this->schedulerCompany->id,
        'creator_id' => User::findOrFail(1)->id,
        'customer_id' => $customer->id,
        'starts_at' => '2026-01-01 12:00:00',
        'status' => RecurringInvoice::ACTIVE,
        'frequency' => '0 12 * * *',
        'next_invoice_at' => '2026-08-02 12:00:00',
        'limit_by' => RecurringInvoice::NONE,
        'limit_count' => null,
        'limit_date' => null,
        'send_automatically' => false,
        'exchange_rate' => 1,
    ]);

    Artisan::call('generate:recurring-invoices');

    expect(Invoice::whereIn('recurring_invoice_id', $recurringInvoices->modelKeys())->count())->toBe(100)
        ->and($recurringInvoices->filter(fn (RecurringInvoice $template) => $template->invoices()->exists()))
        ->toHaveCount(100);
});

test('count and date limits complete templates after the final scheduled occurrence', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00', 'UTC'));
    $countLimited = createSchedulerTemplate($this->schedulerCompany->id, [
        'limit_by' => RecurringInvoice::COUNT,
        'limit_count' => 2,
    ]);
    $dateLimited = createSchedulerTemplate($this->schedulerCompany->id, [
        'limit_by' => RecurringInvoice::DATE,
        'limit_date' => '2026-08-02',
    ]);

    Artisan::call('generate:recurring-invoices');

    expect($countLimited->invoices()->count())->toBe(2)
        ->and($countLimited->fresh()->status)->toBe(RecurringInvoice::COMPLETED)
        ->and($dateLimited->invoices()->count())->toBe(2)
        ->and($dateLimited->fresh()->status)->toBe(RecurringInvoice::COMPLETED);
});

test('automatic sending applies to every generated catch-up invoice', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
    Mail::fake();
    $recurringInvoice = createSchedulerTemplate($this->schedulerCompany->id, [
        'next_invoice_at' => '2026-08-01 00:00:00',
        'send_automatically' => true,
    ]);

    Artisan::call('generate:recurring-invoices');

    Mail::assertSent(SendInvoiceMail::class, 2);
    expect($recurringInvoice->invoices()->where('status', Invoice::STATUS_SENT)->count())->toBe(2);
});

test('mail failures do not regenerate an already committed invoice', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
    Log::spy();
    $invoiceService = Mockery::mock(InvoiceService::class);
    $invoiceService->shouldReceive('send')->once()->andThrow(new RuntimeException('Mail transport failed.'));
    $this->app->instance(InvoiceService::class, $invoiceService);
    $recurringInvoice = createSchedulerTemplate($this->schedulerCompany->id, [
        'frequency' => '0 12 * * *',
        'next_invoice_at' => '2026-08-02 12:00:00',
        'send_automatically' => true,
    ]);

    Artisan::call('generate:recurring-invoices');
    Artisan::call('generate:recurring-invoices');

    expect($recurringInvoice->invoices()->count())->toBe(1)
        ->and($recurringInvoice->fresh()->next_invoice_at)->toBe('2026-08-03 12:00:00');

    Log::shouldHaveReceived('error')->once();
});

test('one broken template is attempted once and does not block healthy templates', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
    Log::spy();
    $broken = createSchedulerTemplate($this->schedulerCompany->id, [
        'frequency' => 'not a cron expression',
        'next_invoice_at' => '2026-08-02 12:00:00',
    ]);
    $healthy = createSchedulerTemplate($this->schedulerCompany->id, [
        'frequency' => '0 12 * * *',
        'next_invoice_at' => '2026-08-02 12:00:00',
    ]);

    Artisan::call('generate:recurring-invoices');

    expect($broken->invoices()->count())->toBe(0)
        ->and($broken->fresh()->next_invoice_at)->toBe('2026-08-02 12:00:00')
        ->and($healthy->invoices()->count())->toBe(1);

    Log::shouldHaveReceived('error')->once();

    $broken->update(['frequency' => '0 12 * * *']);
    Artisan::call('generate:recurring-invoices');

    expect($broken->invoices()->count())->toBe(1);
});
