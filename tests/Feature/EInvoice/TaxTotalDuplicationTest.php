<?php

namespace Tests\Feature\EInvoice;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxTotalDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'test@company.com']);
        $this->company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-tax',
            'owner_id' => $this->user->id,
            'slug' => 'test-company-tax',
            'email' => 'company@test.com',
            'tax_id' => 'FR12345678901',
        ]);
        $this->currency = Currency::create([
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'precision' => '2',
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ]);
        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'tax_id' => 'DE98765432109',
        ]);
    }

    /**
     * Test that there is only ONE TaxTotal element in the generated XML
     * EN 16931 requires a single TaxTotal element
     */
    public function test_only_one_tax_total_element(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-000001',
            'invoice_date' => '2025-04-06',
            'due_date' => '2025-04-13',
            'sub_total' => 5000, // 50.00 USD
            'total' => 5000,    // 50.00 USD
            'tax' => 0,         // 0.00 USD (zero tax)
            'due_amount' => 5000,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 1,
            'price' => 5000,
            'total' => 5000,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Count TaxTotal elements - should be exactly 1
        $taxTotalCount = substr_count($xml, '<cac:TaxTotal>');
        $this->assertEquals(1, $taxTotalCount, 'There should be exactly ONE TaxTotal element in the XML');
    }

    /**
     * Test that there is only ONE TaxTotal element with tax
     */
    public function test_only_one_tax_total_with_tax(): void
    {
        $taxType = TaxType::create([
            'name' => 'VAT 20%',
            'percent' => 20.0,
            'company_id' => $this->company->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-000002',
            'invoice_date' => '2025-04-06',
            'due_date' => '2025-04-13',
            'sub_total' => 5000, // 50.00 USD
            'total' => 6000,     // 60.00 USD
            'tax' => 1000,       // 10.00 USD (20%)
            'due_amount' => 6000,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 1,
            'price' => 5000,
            'total' => 5000,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 1000,
        ]);

        Tax::create([
            'invoice_item_id' => $item->id,
            'tax_type_id' => $taxType->id,
            'name' => $taxType->name,
            'percent' => 20.0,
            'amount' => 1000,
            'base_amount' => 5000,
            'company_id' => $this->company->id,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Count TaxTotal elements - should be exactly 1
        $taxTotalCount = substr_count($xml, '<cac:TaxTotal>');
        $this->assertEquals(1, $taxTotalCount, 'There should be exactly ONE TaxTotal element in the XML, even with taxes');
    }
}
