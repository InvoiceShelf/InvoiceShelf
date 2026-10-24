<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);
    Queue::fake();
});

test('realistic demo payments are allocated and leave invoice balances consistent', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $company = User::where('email', 'demo@invoiceshelf.com')->firstOrFail()->companies()->firstOrFail();
    $payments = Payment::query()->where('company_id', $company->id)->with('allocations')->get();
    $allocatedInvoices = Invoice::query()
        ->where('company_id', $company->id)
        ->whereHas('allocations')
        ->with('allocations')
        ->get();

    expect($payments)->not->toBeEmpty()
        ->and($payments->every(fn (Payment $payment) => $payment->allocations->isNotEmpty()))->toBeTrue()
        ->and(PaymentAllocation::query()->whereIn('payment_id', $payments->modelKeys())->count())->toBe($payments->count());

    foreach ($allocatedInvoices as $invoice) {
        expect((int) $invoice->due_amount)
            ->toBe(max(0, (int) $invoice->total - (int) $invoice->allocations->sum('amount')));
    }
});

test('realistic demo flags exactly the invoices the overdue sweep would', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $company = User::where('email', 'demo@invoiceshelf.com')->firstOrFail()->companies()->firstOrFail();
    $today = Carbon::now()->startOfDay();
    $invoices = Invoice::query()->where('company_id', $company->id)->get();

    // The rule of check:invoices:status: an invoice that left the draft stage,
    // is not completed, and whose due date fell before today.
    $pastDue = $invoices->filter(fn (Invoice $invoice): bool => $invoice->type === Invoice::TYPE_INVOICE
        && ! in_array($invoice->status, [Invoice::STATUS_COMPLETED, Invoice::STATUS_DRAFT], true)
        && Carbon::parse($invoice->due_date)->lt($today));

    expect($pastDue->count())->toBeGreaterThan(4)
        ->and($invoices->filter(fn (Invoice $invoice): bool => (bool) $invoice->overdue)->modelKeys())
        ->toEqualCanonicalizing($pastDue->modelKeys());
});

test('realistic demo provides current-month account activity for every customer', function () {
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    $company = User::where('email', 'demo@invoiceshelf.com')->firstOrFail()->companies()->firstOrFail();
    $from = Carbon::now()->startOfMonth();
    $to = Carbon::now();
    $statementQuery = app(CustomerStatementQuery::class);

    Customer::query()
        ->where('company_id', $company->id)
        ->each(function (Customer $customer) use ($statementQuery, $from, $to): void {
            $statement = $statementQuery->statement(
                $customer,
                CustomerStatementQuery::TYPE_ACTIVITY,
                $from,
                $to,
            );

            expect($statement['entries']->total())->toBeGreaterThan(0);
        });
});
