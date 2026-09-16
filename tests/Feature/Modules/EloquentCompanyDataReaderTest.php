<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Platform\Modules\Infrastructure\EloquentCompanyDataReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

function hostItem(int $companyId, string $name, int $price): Item
{
    return Item::create([
        'name' => $name,
        'description' => $name.' description',
        'price' => $price,
        'company_id' => $companyId,
        'unit_id' => Unit::query()->where('company_id', $companyId)->value('id'),
        'currency_id' => 1,
    ]);
}

test('company data reader searches and finds customers and invoices without crossing companies', function () {
    $reader = new EloquentCompanyDataReader;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();
    $customerA = Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'A Customer']);
    $customerB = Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'B Customer']);
    $invoiceA = Invoice::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id, 'invoice_number' => 'A-100']);
    Invoice::factory()->create(['company_id' => $companyB->id, 'customer_id' => $customerB->id, 'invoice_number' => 'B-100']);

    expect(collect($reader->searchCustomers($companyA->id, 'Customer', 10))->pluck('name'))->toContain('A Customer')->not->toContain('B Customer')
        ->and($reader->findCustomer($companyA->id, $customerB->id))->toBeNull()
        ->and(collect($reader->searchInvoices($companyA->id, null, null, null, 10))->pluck('invoice_number'))->toContain('A-100')->not->toContain('B-100')
        ->and($reader->findInvoice($companyA->id, $invoiceA->invoice_number)['customer']['id'])->toBe($customerA->id)
        ->and($reader->findInvoice($companyB->id, $invoiceA->invoice_number))->toBeNull();
});

test('company data reader returns company scoped payment, category, item, and overdue query payloads', function () {
    $reader = new EloquentCompanyDataReader;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $companyA->id]);
    $category = ExpenseCategory::factory()->create(['company_id' => $companyA->id, 'name' => 'Travel']);
    Expense::factory()->create(['company_id' => $companyA->id, 'expense_category_id' => $category->id, 'amount' => 2200, 'expense_date' => now()->toDateString()]);
    Payment::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customer->id, 'payment_date' => now()->toDateString(), 'amount' => 1200]);
    $item = hostItem($companyA->id, 'Reader Widget', 500);
    $invoice = Invoice::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customer->id, 'overdue' => true, 'due_amount' => 500, 'due_date' => now()->subDay()->toDateString()]);
    InvoiceItem::factory()->create(['company_id' => $companyA->id, 'invoice_id' => $invoice->id, 'item_id' => $item->id, 'quantity' => 2, 'total' => 1000]);
    hostItem($companyB->id, 'Other Company Item', 999);

    expect($reader->recentPayments($companyA->id, now()->subDay()->toDateString(), 10))->toHaveCount(1)
        ->and($reader->expenseCategories($companyA->id))->toContain(['id' => $category->id, 'name' => 'Travel', 'description' => $category->description])
        ->and(collect($reader->searchItems($companyA->id, 'Widget', 10))->pluck('name'))->toContain('Reader Widget')->not->toContain('Other Company Item')
        ->and($reader->overdueInvoices($companyA->id, 10)[0]['id'])->toBe($invoice->id);
});

test('company data reader aggregates stats and rankings with date windows and batched related names', function () {
    Carbon::setTestNow('2026-08-05 12:00:00');
    $reader = new EloquentCompanyDataReader;
    $company = Company::firstOrFail();
    $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Top Customer']);
    $category = ExpenseCategory::factory()->create(['company_id' => $company->id, 'name' => 'Software']);
    $item = hostItem($company->id, 'Top Item', 300);
    $invoice = Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'invoice_date' => '2026-08-03', 'total' => 3000]);
    InvoiceItem::factory()->create(['company_id' => $company->id, 'invoice_id' => $invoice->id, 'item_id' => $item->id, 'quantity' => 10, 'total' => 3000]);
    Payment::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'payment_date' => '2026-08-04', 'amount' => 2500]);
    Expense::factory()->create(['company_id' => $company->id, 'expense_category_id' => $category->id, 'expense_date' => '2026-08-02', 'amount' => 700]);

    $stats = $reader->companyStats($company->id, '2026-08-01', '2026-08-05');

    expect($stats['invoices'])->toBe(['count' => 1, 'total' => 3000.0])
        ->and($stats['payments'])->toBe(['count' => 1, 'total' => 2500.0])
        ->and($stats['expenses'])->toBe(['count' => 1, 'total' => 700.0])
        ->and($reader->rankCustomers($company->id, 'invoiced_total', '2026-08-01', '2026-08-05', 5)[0]['name'])->toBe('Top Customer')
        ->and($reader->rankCustomers($company->id, 'paid_total', '2026-08-01', '2026-08-05', 5)[0]['metric_value'])->toBe(2500.0)
        ->and($reader->rankCustomers($company->id, 'invoice_count', '2026-08-01', '2026-08-05', 5)[0]['invoice_count'])->toBe(1)
        ->and($reader->rankCustomers($company->id, 'outstanding_balance', null, null, 5))->toBeArray()
        ->and($reader->rankExpenseCategories($company->id, '2026-08-01', '2026-08-05', 5)[0]['name'])->toBe('Software')
        ->and($reader->rankItems($company->id, 'revenue', '2026-08-01', '2026-08-05', 5)[0])->toMatchArray(['name' => 'Top Item', 'revenue' => 3000.0]);
});

test('company data reader lists the members of one company ordered by name then id', function () {
    $reader = new EloquentCompanyDataReader;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();
    $zoe = User::factory()->create(['name' => 'Zoe Member', 'email' => 'zoe@example.test']);
    $firstAdam = User::factory()->create(['name' => 'Adam Member', 'email' => 'adam.one@example.test']);
    $secondAdam = User::factory()->create(['name' => 'Adam Member', 'email' => 'adam.two@example.test']);
    $outsider = User::factory()->create(['name' => 'Bea Outsider', 'email' => 'bea@example.test']);
    $zoe->companies()->attach($companyA->id);
    $firstAdam->companies()->attach($companyA->id);
    $secondAdam->companies()->attach($companyA->id);
    $outsider->companies()->attach($companyB->id);
    $seededOwner = User::findOrFail($companyA->owner_id);

    $members = $reader->companyMembers($companyA->id);

    expect(collect($members)->pluck('id')->all())->toBe([$firstAdam->id, $secondAdam->id, $seededOwner->id, $zoe->id])
        ->and(collect($members)->pluck('name')->all())->toBe(['Adam Member', 'Adam Member', $seededOwner->name, 'Zoe Member'])
        ->and($members[0])->toBe(['id' => $firstAdam->id, 'name' => 'Adam Member', 'email' => 'adam.one@example.test', 'avatar' => null])
        ->and(collect($reader->companyMembers($companyB->id))->pluck('email')->all())->toBe(['bea@example.test']);
});

test('company data reader narrows invoice ids to the ones stored in the company', function () {
    $reader = new EloquentCompanyDataReader;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();
    $customerA = Customer::factory()->create(['company_id' => $companyA->id]);
    $customerB = Customer::factory()->create(['company_id' => $companyB->id]);
    $mine = Invoice::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);
    $theirs = Invoice::factory()->create(['company_id' => $companyB->id, 'customer_id' => $customerB->id]);

    $existing = $reader->existingInvoiceIds($companyA->id, [$mine->id, $theirs->id, 987654321]);

    expect($existing)->toBe([(int) $mine->id])
        ->and($existing[0])->toBeInt()
        ->and($reader->existingInvoiceIds($companyA->id, []))->toBe([])
        ->and($reader->existingInvoiceIds($companyA->id, [(string) $mine->id]))->toBe([(int) $mine->id])
        ->and($reader->existingInvoiceIds($companyB->id, [$mine->id]))->toBe([]);
});

test('company data reader reports the customer currency on single and list reads', function () {
    $reader = new EloquentCompanyDataReader;
    $company = Company::firstOrFail();
    $euro = Currency::query()->where('code', 'EUR')->firstOrFail();
    $priced = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Priced Customer', 'currency_id' => $euro->id]);
    $unpriced = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Unpriced Customer', 'currency_id' => null]);

    $found = $reader->findCustomer($company->id, $priced->id);
    $rows = collect($reader->searchCustomers($company->id, 'Customer', 10))->keyBy('id');

    expect($found['currency_id'])->toBe($euro->id)
        ->and($found['currency'])->toBe(['id' => $euro->id, 'code' => 'EUR', 'symbol' => $euro->symbol, 'precision' => $euro->precision])
        ->and($reader->findCustomer($company->id, $unpriced->id))->toMatchArray(['currency_id' => null, 'currency' => null])
        ->and($rows[$priced->id]['currency_id'])->toBe($euro->id)
        ->and($rows[$unpriced->id]['currency_id'])->toBeNull();
});
