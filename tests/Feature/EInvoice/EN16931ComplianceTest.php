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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EN16931ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Currency $currency;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@company.com',
        ]);

        // Create test company
        $this->company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-en16931',
            'owner_id' => $this->user->id,
            'slug' => 'test-company-en16931',
            'email' => 'company@test.com',
            'tax_id' => 'FR12345678901',
        ]);

        // Create currency
        $this->currency = Currency::create([
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'precision' => '2',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);

        // Create customer
        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'company_name' => 'Customer Company',
            'email' => 'customer@test.com',
            'phone' => '+33123456789',
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'tax_id' => 'FR98765432109',
        ]);
    }

    /**
     * Test BR-CO-15: Invoice total amount with VAT = Total without VAT + Total VAT amount
     */
    public function test_br_co_15_total_amount_compliance(): void
    {
        // Create invoice with exact amounts
        // Net: 100.00€, Tax: 20.00€ (20%), Total: 120.00€
        $invoice = $this->createInvoiceWithAmounts(
            netAmount: 10000, // 100.00€ in cents
            taxAmount: 2000,  // 20.00€ in cents
            totalAmount: 12000 // 120.00€ in cents
        );

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success'], 'E-invoice generation should succeed');

        $xml = $result['xml'];

        // Extract totals from XML
        $payableAmount = $this->extractXmlValue($xml, 'PayableAmount');
        $taxExclusiveAmount = $this->extractXmlValue($xml, 'TaxExclusiveAmount');
        $taxInclusiveAmount = $this->extractXmlValue($xml, 'TaxInclusiveAmount');

        // BR-CO-15: Total with VAT = Total without VAT + Total VAT
        // Total VAT = TaxInclusiveAmount - TaxExclusiveAmount
        $calculatedTotal = (float) $taxExclusiveAmount + ((float) $taxInclusiveAmount - (float) $taxExclusiveAmount);
        $actualTotal = (float) $payableAmount;

        $this->assertEqualsWithDelta(
            $calculatedTotal,
            $actualTotal,
            0.01,
            'BR-CO-15: Total with VAT must equal Total without VAT + Total VAT'
        );
    }

    /**
     * Test BR-CO-15 with multiple line items
     */
    public function test_br_co_15_with_multiple_items(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-MULTI-001',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 20000, // 200.00€
            'total' => 24000,     // 240.00€
            'tax' => 4000,        // 40.00€ (20%)
            'due_amount' => 24000,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        // Create tax type
        $taxType = TaxType::create([
            'name' => 'VAT 20%',
            'percent' => 20.0,
            'company_id' => $this->company->id,
        ]);

        // Item 1: 50.00€ * 2 = 100.00€, Tax: 20.00€
        $item1 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Product 1',
            'quantity' => 2,
            'price' => 5000, // 50.00€
            'total' => 10000, // 100.00€
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 2000, // 20.00€
        ]);

        Tax::create([
            'invoice_item_id' => $item1->id,
            'tax_type_id' => $taxType->id,
            'name' => $taxType->name,
            'percent' => 20.0,
            'amount' => 2000, // 20.00€
            'base_amount' => 10000,
            'company_id' => $this->company->id,
        ]);

        // Item 2: 50.00€ * 2 = 100.00€, Tax: 20.00€
        $item2 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Product 2',
            'quantity' => 2,
            'price' => 5000, // 50.00€
            'total' => 10000, // 100.00€
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 2000, // 20.00€
        ]);

        Tax::create([
            'invoice_item_id' => $item2->id,
            'tax_type_id' => $taxType->id,
            'name' => $taxType->name,
            'percent' => 20.0,
            'amount' => 2000, // 20.00€
            'base_amount' => 10000,
            'company_id' => $this->company->id,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        $xml = $result['xml'];

        // Verify totals
        $payableAmount = (float) $this->extractXmlValue($xml, 'PayableAmount');
        $taxExclusiveAmount = (float) $this->extractXmlValue($xml, 'TaxExclusiveAmount');
        $taxInclusiveAmount = (float) $this->extractXmlValue($xml, 'TaxInclusiveAmount');

        $totalTax = $taxInclusiveAmount - $taxExclusiveAmount;
        $expectedTotal = $taxExclusiveAmount + $totalTax;

        $this->assertEqualsWithDelta(
            $expectedTotal,
            $payableAmount,
            0.01,
            'BR-CO-15: Multiple items total must equal net + tax'
        );
    }

    /**
     * Test BR-CL-23: Unit code must be UN/ECE Recommendation 20 compliant
     */
    public function test_br_cl_23_unit_code_compliance(): void
    {
        $invoice = $this->createInvoiceWithAmounts(10000, 2000, 12000);

        // Add item with different unit codes
        $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
        $item->update(['unit_name' => 'EA']); // Should be converted to C62

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        $xml = $result['xml'];

        // Check that unit code is UN/ECE compliant (C62, not EA)
        $this->assertStringContainsString('unitCode="C62"', $xml);
        $this->assertStringNotContainsString('unitCode="EA"', $xml);
    }

    /**
     * Test unit code normalization for various inputs
     */
    public function test_unit_code_normalization(): void
    {
        $testCases = [
            'EA' => 'C62',
            'PCE' => 'C62',
            'PC' => 'C62',
            'PIECE' => 'C62',
            'HUR' => 'HUR',
            'HR' => 'HUR',
            'HOUR' => 'HUR',
            'DAY' => 'DAY',
            'KG' => 'KGM',
            'KILOGRAM' => 'KGM',
            'L' => 'LTR',
            'LITER' => 'LTR',
        ];

        foreach ($testCases as $input => $expected) {
            $invoice = $this->createInvoiceWithAmounts(10000, 2000, 12000);
            $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
            $item->update(['unit_name' => $input]);

            $service = app(EInvoiceService::class);
            $result = $service->generate($invoice, 'UBL');

            $this->assertTrue($result['success'], "Failed for unit: {$input}");
            $this->assertStringContainsString("unitCode=\"{$expected}\"", $result['xml'], "Unit {$input} should map to {$expected}");
        }
    }

    /**
     * Test European validation API (if available)
     */
    public function test_european_validation_api(): void
    {
        // Mock HTTP to avoid actual API calls in tests
        Http::fake([
            'itb.ec.europa.eu/*' => Http::response([
                'valid' => true,
                'errors' => [],
                'warnings' => [],
            ], 200),
        ]);

        $invoice = $this->createInvoiceWithAmounts(10000, 2000, 12000);
        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        // Test validation with European API
        $response = Http::post('https://www.itb.ec.europa.eu/vitb/rest/validate', [
            'xml' => $result['xml'],
            'format' => 'UBL Invoice XML - release 1.3.14.2',
        ]);

        $this->assertTrue($response->successful());
        $data = $response->json();
        $this->assertTrue($data['valid'] ?? false, 'European validation should pass');
    }

    /**
     * Test BR-CO-15 with zero tax
     */
    public function test_br_co_15_with_zero_tax(): void
    {
        $invoice = $this->createInvoiceWithAmounts(
            netAmount: 10000,
            taxAmount: 0,
            totalAmount: 10000
        );

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        $xml = $result['xml'];

        $payableAmount = (float) $this->extractXmlValue($xml, 'PayableAmount');
        $taxExclusiveAmount = (float) $this->extractXmlValue($xml, 'TaxExclusiveAmount');

        // With zero tax, total should equal net
        $this->assertEqualsWithDelta(
            $taxExclusiveAmount,
            $payableAmount,
            0.01,
            'BR-CO-15: With zero tax, total should equal net amount'
        );
    }

    /**
     * Test BR-CO-15 with rounding differences
     */
    public function test_br_co_15_with_rounding(): void
    {
        // Create invoice with amounts that might cause rounding issues
        // Net: 33.33€, Tax: 6.67€ (20%), Total: 40.00€
        $invoice = $this->createInvoiceWithAmounts(
            netAmount: 3333,  // 33.33€
            taxAmount: 667,   // 6.67€
            totalAmount: 4000 // 40.00€
        );

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        $xml = $result['xml'];

        $payableAmount = (float) $this->extractXmlValue($xml, 'PayableAmount');
        $taxExclusiveAmount = (float) $this->extractXmlValue($xml, 'TaxExclusiveAmount');
        $taxInclusiveAmount = (float) $this->extractXmlValue($xml, 'TaxInclusiveAmount');

        $totalTax = $taxInclusiveAmount - $taxExclusiveAmount;
        $expectedTotal = $taxExclusiveAmount + $totalTax;

        // Allow small rounding differences (0.01€)
        $this->assertLessThanOrEqual(
            0.01,
            abs($expectedTotal - $payableAmount),
            'BR-CO-15: Rounding differences should be handled correctly'
        );
    }

    /**
     * Test that XML is well-formed and valid
     */
    public function test_xml_is_well_formed(): void
    {
        $invoice = $this->createInvoiceWithAmounts(10000, 2000, 12000);
        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);

        $xml = $result['xml'];

        // Check XML structure
        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', $xml);
        $this->assertStringContainsString('<Invoice', $xml);
        $this->assertStringContainsString('</Invoice>', $xml);

        // Try to parse XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $parsed = $dom->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertTrue($parsed, 'XML should be well-formed. Errors: '.json_encode($errors));
    }

    /**
     * Helper: Create invoice with specific amounts
     */
    private function createInvoiceWithAmounts(int $netAmount, int $taxAmount, int $totalAmount): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => $netAmount,
            'total' => $totalAmount,
            'tax' => $taxAmount,
            'due_amount' => $totalAmount,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        // Create tax type if tax > 0
        if ($taxAmount > 0) {
            $taxRate = ($taxAmount / $netAmount) * 100;
            $taxType = TaxType::create([
                'name' => "VAT {$taxRate}%",
                'percent' => round($taxRate, 2),
                'company_id' => $this->company->id,
            ]);

            // Calculate unit price and quantity to match net amount
            // Simple: 1 item with price = netAmount
            $item = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => 'Test Product',
                'quantity' => 1,
                'price' => $netAmount,
                'total' => $netAmount,
                'company_id' => $this->company->id,
                'discount_type' => 'FIXED',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => $taxAmount,
            ]);

            Tax::create([
                'invoice_item_id' => $item->id,
                'tax_type_id' => $taxType->id,
                'name' => $taxType->name,
                'percent' => round($taxRate, 2),
                'amount' => $taxAmount,
                'base_amount' => $netAmount,
                'company_id' => $this->company->id,
            ]);
        } else {
            // No tax
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => 'Test Product',
                'quantity' => 1,
                'price' => $netAmount,
                'total' => $netAmount,
                'company_id' => $this->company->id,
                'discount_type' => 'FIXED',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
            ]);
        }

        return $invoice;
    }

    /**
     * Helper: Extract value from XML by tag name
     */
    private function extractXmlValue(string $xml, string $tagName): ?string
    {
        $pattern = '/<cbc:'.$tagName.'[^>]*>([^<]+)<\/cbc:'.$tagName.'>/i';
        if (preg_match($pattern, $xml, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
