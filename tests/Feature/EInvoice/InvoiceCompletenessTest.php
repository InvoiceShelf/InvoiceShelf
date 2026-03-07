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

class InvoiceCompletenessTest extends TestCase
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
            'unique_hash' => 'test-hash-complete',
            'owner_id' => $this->user->id,
            'slug' => 'test-company-complete',
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
     * Test that items with negative amounts (discounts/adjustments) are included
     */
    public function test_negative_amount_items_are_included(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-NEG-001',
            'invoice_date' => '2025-04-06',
            'due_date' => '2025-04-13',
            'sub_total' => 4950, // 49.50 USD (50.00 - 0.50)
            'total' => 4950,
            'tax' => 0,
            'due_amount' => 4950,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        // Item 1: $50.00
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Product',
            'quantity' => 1,
            'price' => 5000, // 50.00 USD
            'total' => 5000,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
        ]);

        // Item 2: -$0.50 (discount/adjustment)
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Discount',
            'quantity' => 1,
            'price' => -50, // -0.50 USD
            'total' => -50, // Negative amount
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

        // Should contain both items
        $this->assertStringContainsString('Product', $xml);
        $this->assertStringContainsString('Discount', $xml);
        
        // Should have correct totals: 50.00 - 0.50 = 49.50
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="USD">49.5</cbc:PayableAmount>', $xml);
    }

    /**
     * Test that invoice-level taxes (like TestFlat) are included
     */
    public function test_invoice_level_taxes_are_included(): void
    {
        $taxType = TaxType::create([
            'name' => 'TestFlat',
            'percent' => 0.0, // Fixed amount tax
            'company_id' => $this->company->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-FLAT-001',
            'invoice_date' => '2025-04-06',
            'due_date' => '2025-04-13',
            'sub_total' => 5000, // 50.00 USD
            'total' => 7000,     // 70.00 USD (50 + 20)
            'tax' => 2000,       // 20.00 USD (TestFlat)
            'due_amount' => 7000,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'NO', // Tax at invoice level
            'discount_per_item' => 'NO',
        ]);

        // Item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Product',
            'quantity' => 1,
            'price' => 5000,
            'total' => 5000,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
        ]);

        // Invoice-level tax (TestFlat)
        Tax::create([
            'invoice_id' => $invoice->id,
            'tax_type_id' => $taxType->id,
            'name' => 'TestFlat',
            'percent' => 0.0,
            'amount' => 2000, // 20.00 USD
            'base_amount' => 5000,
            'company_id' => $this->company->id,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Tax should appear in TaxTotal section
        $this->assertStringContainsString('<cac:TaxTotal>', $xml);
        // Check that TaxAmount of 20 appears in TaxTotal
        // Note: The tax may also appear as an invoice line (which is acceptable for EN 16931),
        // but it MUST appear in TaxTotal
        $this->assertStringContainsString('<cbc:TaxAmount currencyID="USD">20</cbc:TaxAmount>', $xml);
        
        // Should have correct totals: 50.00 + 20.00 = 70.00
        // Extract PayableAmount, TaxExclusiveAmount, and TaxAmount from XML
        preg_match('/<cbc:PayableAmount currencyID="USD">([^<]+)<\/cbc:PayableAmount>/', $xml, $payableMatches);
        preg_match('/<cbc:TaxExclusiveAmount currencyID="USD">([^<]+)<\/cbc:TaxExclusiveAmount>/', $xml, $exclusiveMatches);
        preg_match('/<cbc:TaxAmount currencyID="USD">([^<]+)<\/cbc:TaxAmount>/', $xml, $taxMatches);
        
        $payableAmount = (float) ($payableMatches[1] ?? 0);
        $taxExclusiveAmount = (float) ($exclusiveMatches[1] ?? 0);
        $taxAmount = (float) ($taxMatches[1] ?? 0);
        
        // PayableAmount should equal TaxExclusiveAmount + TaxAmount
        $expectedPayable = round($taxExclusiveAmount + $taxAmount, 2);
        $this->assertEqualsWithDelta(70.0, $payableAmount, 0.01, 
            "Expected PayableAmount to be 70, but got: {$payableAmount}. TaxExclusive: {$taxExclusiveAmount}, Tax: {$taxAmount}, Expected: {$expectedPayable}");
    }

    /**
     * Test complete invoice with items, negative items, and invoice-level taxes
     */
    public function test_complete_invoice_with_all_elements(): void
    {
        $taxType = TaxType::create([
            'name' => 'TestFlat',
            'percent' => 0.0,
            'company_id' => $this->company->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-COMPLETE-001',
            'invoice_date' => '2025-04-06',
            'due_date' => '2025-04-13',
            'sub_total' => 4950, // 49.50 USD (50.00 - 0.50)
            'total' => 6950,     // 69.50 USD (49.50 + 20.00)
            'tax' => 2000,       // 20.00 USD (TestFlat)
            'due_amount' => 6950,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'tax_per_item' => 'NO',
            'discount_per_item' => 'NO',
        ]);

        // Item 1: $50.00
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Product',
            'quantity' => 1,
            'price' => 5000,
            'total' => 5000,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
        ]);

        // Item 2: -$0.50 (discount)
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Discount',
            'quantity' => 1,
            'price' => -50,
            'total' => -50,
            'company_id' => $this->company->id,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
        ]);

        // Invoice-level tax (TestFlat)
        Tax::create([
            'invoice_id' => $invoice->id,
            'tax_type_id' => $taxType->id,
            'name' => 'TestFlat',
            'percent' => 0.0,
            'amount' => 2000,
            'base_amount' => 4950,
            'company_id' => $this->company->id,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Should contain all elements
        $this->assertStringContainsString('Product', $xml);
        $this->assertStringContainsString('Discount', $xml);
        
        // Tax should appear in TaxTotal, not as invoice line
        $this->assertStringContainsString('<cac:TaxTotal>', $xml);
        $this->assertStringContainsString('<cbc:TaxAmount currencyID="USD">20</cbc:TaxAmount>', $xml);
        
        // Should have correct totals: 50.00 - 0.50 + 20.00 = 69.50
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="USD">69.5</cbc:PayableAmount>', $xml);
        
        // Verify BR-CO-15: Total = Net + Tax
        // Net: 49.50, Tax: 20.00, Total: 69.50
        $payableAmount = $this->extractXmlValue($xml, 'TaxInclusiveAmount') ?? $this->extractXmlValue($xml, 'PayableAmount');
        $taxExclusiveAmount = $this->extractXmlValue($xml, 'TaxExclusiveAmount');
        
        // Extract TaxAmount from TaxTotal (not from TaxSubtotal to avoid counting compensation amounts)
        preg_match('/<cac:TaxTotal>.*?<cbc:TaxAmount[^>]*>([^<]+)<\/cbc:TaxAmount>.*?<\/cac:TaxTotal>/s', $xml, $taxTotalMatches);
        $totalTax = (float) ($taxTotalMatches[1] ?? 0);
        
        // If no match, try extracting from all TaxAmount and sum only positive ones from TaxSubtotal
        if ($totalTax == 0) {
            preg_match_all('/<cac:TaxSubtotal>.*?<cbc:TaxableAmount[^>]*>([^<]+)<\/cbc:TaxableAmount>.*?<cbc:TaxAmount[^>]*>([^<]+)<\/cbc:TaxAmount>.*?<\/cac:TaxSubtotal>/s', $xml, $subtotalMatches, PREG_SET_ORDER);
            foreach ($subtotalMatches as $match) {
                $taxableAmount = (float) $match[1];
                $taxAmount = (float) $match[2];
                // Only count TaxSubtotal with positive TaxableAmount (ignore compensation)
                if ($taxableAmount > 0 && $taxAmount > 0) {
                    $totalTax += $taxAmount;
                }
            }
        }
        
        $expectedTotal = (float) $taxExclusiveAmount + $totalTax;
        
        $this->assertEqualsWithDelta(
            $expectedTotal,
            (float) $payableAmount,
            0.01,
            'BR-CO-15: Total must equal Net + Tax'
        );
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
