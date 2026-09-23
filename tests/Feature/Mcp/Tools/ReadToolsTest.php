<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Application\Composition\PaymentComposer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Catalog\SearchItemsTool;
use App\Platform\Mcp\Tools\Contacts\GetCustomerTool;
use App\Platform\Mcp\Tools\Contacts\SearchCustomersTool;
use App\Platform\Mcp\Tools\Purchases\ListExpenseCategoriesTool;
use App\Platform\Mcp\Tools\Purchases\SearchExpensesTool;
use App\Platform\Mcp\Tools\Receivables\GetPaymentTool;
use App\Platform\Mcp\Tools\Receivables\ListRecentPaymentsTool;
use App\Platform\Mcp\Tools\Reporting\GetCompanyStatsTool;
use App\Platform\Mcp\Tools\Reporting\RankExpenseCategoriesTool;
use App\Platform\Mcp\Tools\Reporting\RankTopCustomersTool;
use App\Platform\Mcp\Tools\Reporting\RankTopItemsTool;
use App\Platform\Mcp\Tools\Sales\GetEstimateTool;
use App\Platform\Mcp\Tools\Sales\GetInvoiceTool;
use App\Platform\Mcp\Tools\Sales\ListOverdueInvoicesTool;
use App\Platform\Mcp\Tools\Sales\SearchEstimatesTool;
use App\Platform\Mcp\Tools\Sales\SearchInvoicesTool;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Support\McpTesting;

use function Pest\Laravel\postJson;

/*
 * The books this test reads, as of 23 September 2026 (UTC):
 *
 * - INV-A, Acme, 1 September, due 15 September: Consulting 10 x 100.00 plus
 *   20% VAT = 1200.00, sent, 200.00 paid and 100.00 credited (CN-1), so
 *   900.00 overdue.
 * - INV-B, Globex, 10 September, due 10 October: Support 500.00, sent, unpaid.
 * - INV-C, Acme, 20 September: a 300.00 draft.
 * - CN-1, a 100.00 credit note against INV-A.
 * - An estimate for Acme: Build 250.00.
 * - Expenses: Travel 120.00 and 80.00, Software 50.00.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-23 12:00:00');
    Carbon::setTestNow('2026-09-23 12:00:00');

    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();
    $companyId = $this->company->id;
    $usd = Currency::where('code', 'USD')->value('id');

    CompanySetting::setSettings([
        'currency' => $usd,
        'time_zone' => 'UTC',
        'fiscal_year' => '1-12',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'invoice_set_due_date_automatically' => 'NO',
    ], $companyId);

    $this->withHeaders(['company' => $companyId]);
    Sanctum::actingAs($this->user, ['*']);

    $this->acme = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Acme Corp', 'email' => 'ap@acme.test', 'currency_id' => $usd]);
    $this->globex = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Globex', 'currency_id' => $usd]);
    $vat = TaxType::factory()->create(['company_id' => $companyId, 'name' => 'VAT', 'percent' => 20, 'type' => TaxType::TYPE_GENERAL]);
    $this->consulting = Item::factory()->create(['company_id' => $companyId, 'name' => 'Consulting', 'price' => 10000, 'currency_id' => $usd]);

    $composer = app(SalesDocumentComposer::class);
    $issue = function (array $intent, bool $send = true) use ($composer, $companyId) {
        $id = postJson('api/v1/invoices', $composer->invoice($intent, $companyId, $this->user))->assertOk()->json('data.id');
        $invoice = Invoice::find($id);

        if ($send) {
            $invoice->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);
        }

        return $invoice;
    };

    $this->invoiceA = $issue(['customer_id' => $this->acme->id, 'number' => 'INV-A', 'date' => '2026-09-01', 'due_date' => '2026-09-15', 'reference_number' => 'PO-77',
        'lines' => [['item_id' => $this->consulting->id, 'quantity' => 10]], 'tax_type_ids' => [$vat->id]]);
    $this->invoiceB = $issue(['customer_id' => $this->globex->id, 'number' => 'INV-B', 'date' => '2026-09-10', 'due_date' => '2026-10-10',
        'lines' => [['name' => 'Support', 'unit_price' => '500']]]);
    $this->invoiceC = $issue(['customer_id' => $this->acme->id, 'number' => 'INV-C', 'date' => '2026-09-20',
        'lines' => [['name' => 'Workshop', 'unit_price' => '300']]], send: false);

    $this->creditNote = Invoice::factory()->create([
        'company_id' => $companyId, 'customer_id' => $this->acme->id, 'currency_id' => $usd, 'invoice_number' => 'CN-1',
        'type' => Invoice::TYPE_CREDIT_NOTE, 'related_invoice_id' => $this->invoiceA->id, 'status' => Invoice::STATUS_SENT,
        'invoice_date' => '2026-09-05', 'total' => -10000, 'base_total' => -10000, 'due_amount' => 0, 'base_due_amount' => 0, 'exchange_rate' => 1,
    ]);

    $paymentId = postJson('api/v1/payments', app(PaymentComposer::class)->payment([
        'amount' => '200', 'date' => '2026-09-12', 'payment_method' => 'Bank Transfer',
        'allocations' => [['invoice_number' => 'INV-A']],
    ], $companyId))->assertSuccessful()->json('data.id');
    $this->payment = Payment::find($paymentId);

    $this->estimateId = postJson('api/v1/estimates', $composer->estimate([
        'customer_id' => $this->acme->id, 'number' => 'EST-1', 'date' => '2026-09-18', 'lines' => [['name' => 'Build', 'unit_price' => '250']],
    ], $companyId, $this->user))->assertSuccessful()->json('data.id');

    $travel = ExpenseCategory::factory()->create(['company_id' => $companyId, 'name' => 'Travel']);
    $software = ExpenseCategory::factory()->create(['company_id' => $companyId, 'name' => 'Software']);

    foreach ([[$travel, 12000, 'Train to Berlin'], [$travel, 8000, 'Taxi'], [$software, 5000, 'Editor licence']] as [$category, $amount, $notes]) {
        Expense::factory()->create([
            'company_id' => $companyId, 'expense_category_id' => $category->id, 'amount' => $amount, 'base_amount' => $amount,
            'exchange_rate' => 1, 'currency_id' => $usd, 'expense_date' => '2026-09-08', 'notes' => $notes, 'customer_id' => null,
        ]);
    }

    McpTesting::actAs($this->user, $companyId, McpConnection::ACCESS_READ);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

test('customers are found by name and paged', function () {
    $found = McpTesting::call($this->user, SearchCustomersTool::class, ['query' => 'acme']);
    $firstPage = McpTesting::call($this->user, SearchCustomersTool::class, ['limit' => 1]);

    expect(array_column($found['customers'], 'name'))->toBe(['Acme Corp'])
        ->and($found['customers'][0])->toMatchArray(['email' => 'ap@acme.test', 'currency' => 'USD'])
        ->and($found['customers'][0]['app_url'])->toEndWith("/admin/customers/{$this->acme->id}/view")
        ->and($firstPage['customers'])->toHaveCount(1)
        ->and($firstPage['has_more'])->toBeTrue();
});

test('a customer comes with what they owe', function () {
    $customer = McpTesting::call($this->user, GetCustomerTool::class, ['customer_id' => $this->acme->id]);

    expect($customer['balance']['outstanding'])->toBe(['amount' => '900.00', 'currency' => 'USD', 'formatted' => '$900.00'])
        ->and($customer['balance']['overdue']['amount'])->toBe('900.00')
        ->and($customer['balance']['open_invoices'])->toBe(1);
});

test('a customer of another company is not found', function () {
    $elsewhere = Customer::factory()->create(['company_id' => Company::factory()->create()->id]);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetCustomerTool::class, ['customer_id' => $elsewhere->id])
        ->assertHasErrors(['There is no customer with this id in the company.']);
});

test('items carry their price in major units', function () {
    $items = McpTesting::call($this->user, SearchItemsTool::class, ['query' => 'consult']);

    expect($items['items'][0])->toMatchArray(['name' => 'Consulting', 'price' => ['amount' => '100.00', 'currency' => 'USD', 'formatted' => '$100.00']]);
});

test('invoice search leaves credit notes out unless asked', function () {
    $invoices = McpTesting::call($this->user, SearchInvoicesTool::class);
    $withNotes = McpTesting::call($this->user, SearchInvoicesTool::class, ['include_credit_notes' => true]);

    expect(array_column($invoices['invoices'], 'number'))->toBe(['INV-C', 'INV-B', 'INV-A'])
        ->and(array_column($withNotes['invoices'], 'number'))->toContain('CN-1')
        ->and(collect($withNotes['invoices'])->firstWhere('number', 'CN-1')['kind'])->toBe('credit_note');
});

test('invoices are found by number, reference, customer, status and date', function (array $arguments, array $numbers) {
    expect(array_column(McpTesting::call($this->user, SearchInvoicesTool::class, $arguments)['invoices'], 'number'))->toBe($numbers);
})->with([
    'number' => [['query' => 'INV-B'], ['INV-B']],
    'reference' => [['query' => 'PO-77'], ['INV-A']],
    'customer name' => [['query' => 'Globex'], ['INV-B']],
    'sending status' => [['status' => 'DRAFT'], ['INV-C']],
    'payment status' => [['status' => 'PARTIALLY_PAID'], ['INV-A']],
    'overdue' => [['status' => 'OVERDUE'], ['INV-A']],
    'date window' => [['from_date' => '2026-09-10', 'to_date' => '2026-09-20'], ['INV-C', 'INV-B']],
]);

test('an invoice is read in full by number or id', function () {
    $byNumber = McpTesting::call($this->user, GetInvoiceTool::class, ['invoice_number' => 'INV-A']);
    $byId = McpTesting::call($this->user, GetInvoiceTool::class, ['invoice_id' => $this->invoiceA->id]);

    expect($byNumber)->toBe($byId)
        ->and($byNumber)->toMatchArray([
            'number' => 'INV-A',
            'date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'reference_number' => 'PO-77',
            'paid_status' => Invoice::STATUS_PARTIALLY_PAID,
        ])
        ->and($byNumber['lines'][0])->toMatchArray(['name' => 'Consulting', 'quantity' => 10, 'item_id' => $this->consulting->id])
        ->and($byNumber['lines'][0]['unit_price']['amount'])->toBe('100.00')
        ->and($byNumber['sub_total']['amount'])->toBe('1000.00')
        ->and($byNumber['taxes'][0])->toMatchArray(['name' => 'VAT', 'percent' => 20.0, 'compound' => false])
        ->and($byNumber['taxes'][0]['amount']['amount'])->toBe('200.00')
        ->and($byNumber['total']['amount'])->toBe('1200.00')
        ->and($byNumber['due']['amount'])->toBe('900.00')
        ->and($byNumber['payments'][0]['amount']['amount'])->toBe('200.00')
        ->and($byNumber['payments'][0]['date'])->toBe('2026-09-12')
        ->and($byNumber['credit_notes'][0]['number'])->toBe('CN-1');
});

test('an invoice that does not exist is an error', function () {
    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetInvoiceTool::class, ['invoice_number' => 'INV-404'])
        ->assertHasErrors(['There is no such invoice in the company.']);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetInvoiceTool::class, [])
        ->assertHasErrors();
});

test('overdue invoices are the issued ones still owing past their due date', function () {
    $overdue = McpTesting::call($this->user, ListOverdueInvoicesTool::class);

    expect(array_column($overdue['invoices'], 'number'))->toBe(['INV-A'])
        ->and($overdue['invoices'][0]['days_overdue'])->toBe(8)
        ->and($overdue['total_overdue']['amount'])->toBe('900.00');
});

test('estimates are found and read', function () {
    $estimates = McpTesting::call($this->user, SearchEstimatesTool::class, ['customer_id' => $this->acme->id]);
    $estimate = McpTesting::call($this->user, GetEstimateTool::class, ['estimate_number' => 'EST-1']);

    expect(array_column($estimates['estimates'], 'number'))->toBe(['EST-1'])
        ->and($estimate['total']['amount'])->toBe('250.00')
        ->and($estimate['lines'][0]['name'])->toBe('Build');
});

test('payments are listed and read with what they settle', function () {
    $payments = McpTesting::call($this->user, ListRecentPaymentsTool::class);
    $payment = McpTesting::call($this->user, GetPaymentTool::class, ['payment_id' => $this->payment->id]);

    expect($payments['payments'][0])->toMatchArray(['number' => $this->payment->payment_number, 'date' => '2026-09-12', 'method' => 'Bank Transfer'])
        ->and($payment['allocations'])->toBe([['invoice_id' => $this->invoiceA->id, 'invoice_number' => 'INV-A', 'amount' => ['amount' => '200.00', 'currency' => 'USD', 'formatted' => '$200.00']]])
        ->and($payment['unapplied']['amount'])->toBe('0.00');
});

test('expenses are searched by category and words, and categories are listed', function () {
    $categories = McpTesting::call($this->user, ListExpenseCategoriesTool::class);
    $travelId = collect($categories['categories'])->firstWhere('name', 'Travel')['id'];

    expect(array_column(McpTesting::call($this->user, SearchExpensesTool::class, ['category_id' => $travelId])['expenses'], 'notes'))
        ->toEqualCanonicalizing(['Train to Berlin', 'Taxi'])
        ->and(array_column(McpTesting::call($this->user, SearchExpensesTool::class, ['query' => 'licence'])['expenses'], 'notes'))
        ->toBe(['Editor licence']);
});

test('company figures count issued invoices less credit notes, and what is owed now', function () {
    $stats = McpTesting::call($this->user, GetCompanyStatsTool::class, ['period' => 'this_month']);

    expect($stats['period'])->toBe(['from' => '2026-09-01', 'to' => '2026-09-30', 'name' => 'this_month'])
        // INV-A and INV-B issued (1200 + 500), the draft left out, CN-1 taken off.
        ->and($stats['invoiced']['amount'])->toBe('1600.00')
        ->and($stats['invoice_count'])->toBe(2)
        ->and($stats['credited']['amount'])->toBe('100.00')
        ->and($stats['received']['amount'])->toBe('200.00')
        ->and($stats['expenses']['amount'])->toBe('250.00')
        ->and($stats['net_income']['amount'])->toBe('-50.00')
        ->and($stats['receivables_now']['outstanding']['amount'])->toBe('1400.00')
        ->and($stats['receivables_now']['overdue']['amount'])->toBe('900.00')
        ->and($stats['receivables_now']['due_within_30_days']['amount'])->toBe('500.00');
});

test('a period that holds nothing still reports what is owed now', function () {
    $stats = McpTesting::call($this->user, GetCompanyStatsTool::class, ['from_date' => '2025-01-01', 'to_date' => '2025-12-31']);

    expect($stats['invoiced']['amount'])->toBe('0.00')
        ->and($stats['period']['name'])->toBe('custom')
        ->and($stats['receivables_now']['outstanding']['amount'])->toBe('1400.00');
});

test('customers, items and spending are ranked', function () {
    $invoiced = McpTesting::call($this->user, RankTopCustomersTool::class, ['period' => 'this_year']);
    $owing = McpTesting::call($this->user, RankTopCustomersTool::class, ['metric' => 'outstanding_balance']);
    $items = McpTesting::call($this->user, RankTopItemsTool::class, ['period' => 'this_year']);
    $spending = McpTesting::call($this->user, RankExpenseCategoriesTool::class, ['period' => 'this_year']);

    expect(array_column($invoiced['customers'], 'name'))->toBe(['Acme Corp', 'Globex'])
        ->and($invoiced['customers'][0]['value']['amount'])->toBe('1100.00')
        ->and(array_column($owing['customers'], 'name'))->toBe(['Acme Corp', 'Globex'])
        ->and($owing['period']['name'])->toBe('now')
        ->and(array_column($items['items'], 'name'))->toBe(['Consulting', 'Support'])
        ->and($items['items'][0]['quantity'])->toEqual(10)
        ->and(array_column($spending['categories'], 'name'))->toBe(['Travel', 'Software'])
        ->and($spending['categories'][0]['spent']['amount'])->toBe('200.00');
});

test('a bad period is refused with the choices', function () {
    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetCompanyStatsTool::class, ['period' => 'next_decade'])
        ->assertHasErrors();

    InvoiceShelfServer::actingAs($this->user)
        ->tool(GetCompanyStatsTool::class, ['from_date' => '2026-09-30', 'to_date' => '2026-09-01'])
        ->assertHasErrors();
});
