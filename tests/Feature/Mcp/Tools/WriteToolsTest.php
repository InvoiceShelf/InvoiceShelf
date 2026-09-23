<?php

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use App\Platform\Mcp\Tools\Catalog\CreateItemTool;
use App\Platform\Mcp\Tools\Catalog\UpdateItemTool;
use App\Platform\Mcp\Tools\Contacts\CreateCustomerTool;
use App\Platform\Mcp\Tools\Contacts\UpdateCustomerTool;
use App\Platform\Mcp\Tools\McpWriteTool;
use App\Platform\Mcp\Tools\Purchases\CreateExpenseTool;
use App\Platform\Mcp\Tools\Receivables\RecordPaymentTool;
use App\Platform\Mcp\Tools\Sales\ChangeEstimateStatusTool;
use App\Platform\Mcp\Tools\Sales\ChangeInvoiceStatusTool;
use App\Platform\Mcp\Tools\Sales\CloneInvoiceTool;
use App\Platform\Mcp\Tools\Sales\ConvertEstimateToInvoiceTool;
use App\Platform\Mcp\Tools\Sales\CreateEstimateTool;
use App\Platform\Mcp\Tools\Sales\CreateInvoiceTool;
use App\Platform\Mcp\Tools\Sales\PreviewDocumentTool;
use App\Platform\Mcp\Tools\Sales\UpdateEstimateTool;
use App\Platform\Mcp\Tools\Sales\UpdateInvoiceTool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\McpTesting;

beforeEach(function () {
    Queue::fake();

    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->companyId = $this->user->companies()->first()->id;
    $this->usd = Currency::where('code', 'USD')->value('id');

    CompanySetting::setSettings([
        'currency' => $this->usd,
        'time_zone' => 'UTC',
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'invoice_set_due_date_automatically' => 'YES',
        'invoice_due_date_days' => '14',
        'estimate_convert_action' => 'no_action',
    ], $this->companyId);

    $this->customer = Customer::factory()->create(['company_id' => $this->companyId, 'name' => 'Acme Corp', 'email' => 'ap@acme.test', 'currency_id' => $this->usd]);
    $this->vat = TaxType::factory()->create(['company_id' => $this->companyId, 'name' => 'VAT', 'percent' => 20, 'type' => TaxType::TYPE_GENERAL]);

    McpTesting::actAs($this->user, $this->companyId, McpConnection::ACCESS_WRITE);
});

function tenHoursOfConsulting(array $overrides = []): array
{
    return array_merge([
        'customer_id' => test()->customer->id,
        'lines' => [['name' => 'Consulting', 'quantity' => 10, 'unit_price' => '120.00']],
        'tax_type_ids' => [test()->vat->id],
    ], $overrides);
}

test('an invoice is created from a description, and the preview said so first', function () {
    $preview = McpTesting::call($this->user, PreviewDocumentTool::class, tenHoursOfConsulting());
    $invoice = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting());

    expect($preview['total'])->toBe($invoice['total'])
        ->and($preview['number'])->toBe($invoice['number'])
        ->and($invoice['total']['amount'])->toBe('1440.00')
        ->and($invoice['taxes'][0]['amount']['amount'])->toBe('240.00')
        ->and($invoice['status'])->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice['due_date'])->toBe(now('UTC')->addDays(14)->toDateString())
        ->and(Invoice::query()->count())->toBe(1);
});

test('the same idempotency key does not create a second invoice', function () {
    $first = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting(['idempotency_key' => 'retry-1']));
    $again = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting(['idempotency_key' => 'retry-1']));
    $other = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting(['idempotency_key' => 'retry-2']));

    expect($again['id'])->toBe($first['id'])
        ->and($again['replayed'])->toBeTrue()
        ->and($other['id'])->not->toBe($first['id'])
        ->and(Invoice::query()->count())->toBe(2);
});

test('an intent the composer refuses comes back as a readable error and stores nothing', function () {
    $purchases = TaxType::factory()->create(['company_id' => $this->companyId, 'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES, 'type' => TaxType::TYPE_GENERAL]);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateInvoiceTool::class, tenHoursOfConsulting(['tax_type_ids' => [$purchases->id]]))
        ->assertHasErrors(["Tax type {$purchases->id} is not one of the company's sales taxes."]);

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateInvoiceTool::class, tenHoursOfConsulting(['number' => 'INV-X']))
        ->assertHasNoErrors();

    // The number is taken: the form request's own rule says so.
    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateInvoiceTool::class, tenHoursOfConsulting(['number' => 'INV-X']))
        ->assertHasErrors();

    expect(Invoice::query()->count())->toBe(1);
});

test('an invoice is changed where told and recomputed', function () {
    $created = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting(['notes' => 'Thanks']));

    $changed = McpTesting::call($this->user, UpdateInvoiceTool::class, ['invoice_number' => $created['number'], 'discount' => '200']);

    expect($changed['lines'][0]['name'])->toBe('Consulting')
        ->and($changed['notes'])->toBe('Thanks')
        ->and($changed['discount']['amount']['amount'])->toBe('200.00')
        ->and($changed['total']['amount'])->toBe('1200.00');
});

test('a credit note cannot be changed or copied', function () {
    $note = Invoice::factory()->create(['company_id' => $this->companyId, 'customer_id' => $this->customer->id, 'type' => Invoice::TYPE_CREDIT_NOTE, 'invoice_number' => 'CN-1']);

    InvoiceShelfServer::actingAs($this->user)->tool(UpdateInvoiceTool::class, ['invoice_number' => 'CN-1', 'notes' => 'x'])->assertHasErrors(['CN-1 is a credit note, which cannot be changed.']);
    InvoiceShelfServer::actingAs($this->user)->tool(CloneInvoiceTool::class, ['invoice_number' => 'CN-1'])->assertHasErrors(['CN-1 is a credit note, which cannot be copied.']);

    expect($note->fresh()->type)->toBe(Invoice::TYPE_CREDIT_NOTE);
});

test('an invoice is marked sent, and completed only once paid', function () {
    $created = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting());

    expect(McpTesting::call($this->user, ChangeInvoiceStatusTool::class, ['invoice_id' => $created['id'], 'status' => 'SENT'])['status'])->toBe('SENT');

    InvoiceShelfServer::actingAs($this->user)
        ->tool(ChangeInvoiceStatusTool::class, ['invoice_id' => $created['id'], 'status' => 'COMPLETED'])
        ->assertHasErrors();

    McpTesting::call($this->user, RecordPaymentTool::class, ['allocations' => [['invoice_id' => $created['id']]]]);

    expect(McpTesting::call($this->user, ChangeInvoiceStatusTool::class, ['invoice_id' => $created['id'], 'status' => 'COMPLETED'])['status'])->toBe('COMPLETED');
});

test('an invoice is copied into a new draft', function () {
    $created = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting());
    $copy = McpTesting::call($this->user, CloneInvoiceTool::class, ['invoice_id' => $created['id']]);

    expect($copy['id'])->not->toBe($created['id'])
        ->and($copy['number'])->not->toBe($created['number'])
        ->and($copy['total'])->toBe($created['total']);
});

test('a payment for an invoice settles it', function () {
    $created = McpTesting::call($this->user, CreateInvoiceTool::class, tenHoursOfConsulting());
    Invoice::find($created['id'])->update(['status' => Invoice::STATUS_SENT, 'sent' => true]);

    $payment = McpTesting::call($this->user, RecordPaymentTool::class, [
        'allocations' => [['invoice_number' => $created['number']]],
        'payment_method' => 'bank transfer',
        'idempotency_key' => 'pay-1',
    ]);
    McpTesting::call($this->user, RecordPaymentTool::class, ['allocations' => [['invoice_number' => $created['number']]], 'idempotency_key' => 'pay-1']);

    expect($payment['amount']['amount'])->toBe('1440.00')
        ->and($payment['method'])->toBe('Bank Transfer')
        ->and($payment['allocations'][0]['invoice_number'])->toBe($created['number'])
        ->and(Payment::query()->count())->toBe(1)
        ->and(Invoice::find($created['id'])->paid_status)->toBe(Invoice::STATUS_PAID);
});

test('estimates are created, changed, marked and turned into invoices', function () {
    $estimate = McpTesting::call($this->user, CreateEstimateTool::class, tenHoursOfConsulting());
    $changed = McpTesting::call($this->user, UpdateEstimateTool::class, ['estimate_id' => $estimate['id'], 'lines' => [['name' => 'Workshop', 'unit_price' => '500']]]);
    $accepted = McpTesting::call($this->user, ChangeEstimateStatusTool::class, ['estimate_id' => $estimate['id'], 'status' => 'ACCEPTED']);
    $invoice = McpTesting::call($this->user, ConvertEstimateToInvoiceTool::class, ['estimate_number' => $estimate['number']]);

    expect($changed['lines'][0]['name'])->toBe('Workshop')
        ->and($changed['total']['amount'])->toBe('600.00')
        ->and($accepted['status'])->toBe('ACCEPTED')
        ->and($invoice['total']['amount'])->toBe('600.00')
        ->and($invoice['lines'][0]['name'])->toBe('Workshop')
        ->and(Estimate::query()->count())->toBe(1);
});

test('an estimate status outside the three is refused', function () {
    $estimate = McpTesting::call($this->user, CreateEstimateTool::class, tenHoursOfConsulting());

    InvoiceShelfServer::actingAs($this->user)
        ->tool(ChangeEstimateStatusTool::class, ['estimate_id' => $estimate['id'], 'status' => 'WHATEVER'])
        ->assertHasErrors();

    expect(Estimate::find($estimate['id'])->status)->toBe(Estimate::STATUS_DRAFT);
});

test('converting an estimate the company deletes needs confirm', function () {
    CompanySetting::setSettings(['estimate_convert_action' => 'delete_estimate'], $this->companyId);
    $estimate = McpTesting::call($this->user, CreateEstimateTool::class, tenHoursOfConsulting());

    InvoiceShelfServer::actingAs($this->user)
        ->tool(ConvertEstimateToInvoiceTool::class, ['estimate_id' => $estimate['id']])
        ->assertHasErrors();

    expect(Invoice::query()->count())->toBe(0);

    McpTesting::call($this->user, ConvertEstimateToInvoiceTool::class, ['estimate_id' => $estimate['id'], 'confirm' => true]);

    expect(Invoice::query()->count())->toBe(1)
        ->and(Estimate::query()->count())->toBe(0);
});

test('customers are created and changed field by field', function () {
    $created = McpTesting::call($this->user, CreateCustomerTool::class, [
        'name' => 'Globex',
        'email' => 'billing@globex.test',
        'currency' => 'EUR',
        'billing_address' => ['street_1' => 'Hauptstrasse 1', 'city' => 'Berlin', 'country' => 'DE'],
    ]);

    $changed = McpTesting::call($this->user, UpdateCustomerTool::class, [
        'customer_id' => $created['id'],
        'phone' => '+49 30 1234',
        'billing_address' => ['zip' => '10115'],
    ]);

    $stored = Customer::with('billingAddress.country')->find($created['id']);

    expect($created['currency'])->toBe('EUR')
        ->and($changed['phone'])->toBe('+49 30 1234')
        ->and($changed['email'])->toBe('billing@globex.test')
        ->and($stored->billingAddress->only(['address_street_1', 'city', 'zip']))->toBe(['address_street_1' => 'Hauptstrasse 1', 'city' => 'Berlin', 'zip' => '10115'])
        ->and($stored->billingAddress->country->code)->toBe('DE');

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateCustomerTool::class, ['name' => 'Globex again', 'email' => 'billing@globex.test'])
        ->assertHasErrors();
});

test('items are created and changed, keeping their taxes unless told', function () {
    CompanySetting::setSettings(['tax_per_item' => 'YES'], $this->companyId);
    Unit::factory()->create(['company_id' => $this->companyId, 'name' => 'hours']);

    $created = McpTesting::call($this->user, CreateItemTool::class, ['name' => 'Support', 'price' => '95.00', 'unit' => 'Hours', 'tax_type_ids' => [$this->vat->id]]);
    $changed = McpTesting::call($this->user, UpdateItemTool::class, ['item_id' => $created['id'], 'price' => '99.50']);

    expect($created['price']['amount'])->toBe('95.00')
        ->and($created['unit'])->toBe('hours')
        ->and($changed['price']['amount'])->toBe('99.50')
        ->and(array_column($changed['taxes'], 'name'))->toBe(['VAT'])
        ->and(Item::find($created['id'])->taxes()->count())->toBe(1);
});

test('expenses are recorded by category name, and another currency needs a rate', function () {
    ExpenseCategory::factory()->create(['company_id' => $this->companyId, 'name' => 'Travel']);

    $expense = McpTesting::call($this->user, CreateExpenseTool::class, ['category' => 'travel', 'amount' => '42.50', 'date' => '2026-09-01', 'notes' => 'Taxi']);

    expect($expense['amount']['amount'])->toBe('42.50')
        ->and($expense['category']['name'])->toBe('Travel')
        ->and($expense['date'])->toBe('2026-09-01');

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateExpenseTool::class, ['category' => 'Travel', 'amount' => '10', 'currency' => 'EUR'])
        ->assertHasErrors();

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateExpenseTool::class, ['category' => 'Meals', 'amount' => '10'])
        ->assertHasErrors(['The expense category is one of: Travel.']);

    expect(Expense::query()->count())->toBe(1);
});

test('a connection may make a limited number of changes a minute', function () {
    $connection = McpConnection::query()->latest('id')->first();

    for ($i = 0; $i < McpWriteTool::WRITES_PER_MINUTE; $i++) {
        RateLimiter::hit('mcp-writes:'.$connection->id, 60);
    }

    InvoiceShelfServer::actingAs($this->user)
        ->tool(CreateCustomerTool::class, ['name' => 'One too many'])
        ->assertHasErrors();

    expect(Customer::query()->where('name', 'One too many')->exists())->toBeFalse();
});
