<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * What the reports put on the page, asserted through ?preview=true so the
 * rendered view is inspectable rather than a PDF byte stream. The page geometry
 * they share is covered in tests/Unit/PdfStockTemplatePageSetupTest.php.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->company = $user->companies()->firstOrFail();

    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

function reportPreview(string $report, string $companyHash, string $from, string $to): TestResponse
{
    return get("/reports/{$report}/{$companyHash}?from_date={$from}&to_date={$to}&preview=true");
}

function reportCustomer(int $companyId, string $name): Customer
{
    return Customer::factory()->create([
        'company_id' => $companyId,
        'name' => $name,
    ]);
}

function reportCustomerInvoice(Customer $customer, string $date, array $attributes = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'invoice_date' => $date,
        'base_total' => 100000,
    ], $attributes));
}

/**
 * The drifted templates looped over an empty collection and then printed a total
 * underneath it, so a period with nothing in it read as a table that had lost
 * its rows rather than as a period with no records.
 */
test('a report with nothing in the period says so rather than showing a bare total', function (string $report) {
    reportPreview($report, $this->company->unique_hash, '2019-01-01', '2019-01-31')
        ->assertOk()
        ->assertSee(__('pdf_report_no_records'));
})->with([
    'sales/customers',
    'sales/items',
    'expenses',
    'tax-summary',
    'profit-loss',
    'purchases',
]);

/**
 * The customer list and the invoices relation are narrowed to the period in two
 * separate places in the controller, and only a customer with documents is worth
 * a heading and a total. Asserted end to end so it holds however the two are
 * wired.
 */
test('a customer with no documents in the period is left out of the customer sales report', function () {
    $inRange = reportCustomer($this->company->id, 'In Range Trading');
    reportCustomerInvoice($inRange, '2026-01-15');

    $outOfRange = reportCustomer($this->company->id, 'Out Of Range Trading');
    reportCustomerInvoice($outOfRange, '2025-01-15');

    reportCustomer($this->company->id, 'Never Invoiced Trading');

    reportPreview('sales/customers', $this->company->unique_hash, '2026-01-01', '2026-01-31')
        ->assertOk()
        ->assertSee('In Range Trading')
        ->assertDontSee('Out Of Range Trading')
        ->assertDontSee('Never Invoiced Trading');
});

/**
 * A credit note is an invoice row of another type, so the report listed it with
 * a CN- number and a negative amount as though it were a sale. It stays in the
 * totals, because a reversal netting the sale out is what the dashboard does
 * too, but the line is tagged so it is not read as one.
 */
test('a credit note is tagged in the customer sales report and still nets out the period', function () {
    $customer = reportCustomer($this->company->id, 'Reversal Holdings');
    reportCustomerInvoice($customer, '2026-01-10', ['base_total' => 500000]);
    $creditNote = reportCustomerInvoice($customer, '2026-01-20', [
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'invoice_number' => 'CN-000042',
        'base_total' => -200000,
    ]);

    reportPreview('sales/customers', $this->company->unique_hash, '2026-01-01', '2026-01-31')
        ->assertOk()
        ->assertSee($creditNote->invoice_number)
        ->assertSee(__('pdf_credit_note_label'))
        ->assertViewHas('totalAmount', 300000);
});

/**
 * An ordinary sale carries no tag, so the one on the credit note means
 * something.
 */
test('an ordinary sale carries no credit note tag', function () {
    $customer = reportCustomer($this->company->id, 'Straightforward Supplies');
    reportCustomerInvoice($customer, '2026-01-10');

    reportPreview('sales/customers', $this->company->unique_hash, '2026-01-01', '2026-01-31')
        ->assertOk()
        ->assertDontSee(__('pdf_credit_note_label'));
});

/**
 * The item sales report used to emit a table per item, and separate tables size
 * their columns on their own content, so no two rows lined up.
 */
test('the item sales report puts every item in one table', function () {
    $customer = reportCustomer($this->company->id, 'Single Table Ltd');
    $invoice = reportCustomerInvoice($customer, '2026-01-15');

    foreach (['Alpha Widget', 'Beta Widget', 'Gamma Widget'] as $name) {
        InvoiceItem::factory()->create([
            'company_id' => $this->company->id,
            'invoice_id' => $invoice->id,
            'name' => $name,
        ]);
    }

    $response = reportPreview('sales/items', $this->company->unique_hash, '2026-01-01', '2026-01-31');

    $response->assertOk()
        ->assertSee('Alpha Widget')
        ->assertSee('Beta Widget')
        ->assertSee('Gamma Widget')
        // The quantity column is only there because itemAttributes() already
        // sums it, so no report query had to grow to carry it.
        ->assertSee(__('pdf_quantity_label'));

    expect(substr_count($response->getContent(), 'class="report-table"'))->toBe(1);
});

/**
 * Every report gets the same branded header as the documents do. Four of the
 * five never shared the logo path, so they fell back to printing the company
 * name where the logo belongs.
 */
test('every report shares the company logo with its header', function (string $report) {
    reportPreview($report, $this->company->unique_hash, '2026-01-01', '2026-01-31')
        ->assertOk()
        ->assertViewHas('logo');
})->with([
    'sales/customers',
    'sales/items',
    'expenses',
    'tax-summary',
    'profit-loss',
    'purchases',
]);
