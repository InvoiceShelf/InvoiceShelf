<?php

namespace Tests\Feature\EInvoice;

use App\Models\Invoice;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealInvoiceTest extends TestCase
{
    // Don't use RefreshDatabase - we want to test with real data from local database

    protected EInvoiceService $eInvoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eInvoiceService = app(EInvoiceService::class);
    }

    /**
     * Test with a real invoice from the database
     */
    public function test_real_invoice_from_database(): void
    {
        $invoice = Invoice::with(['items', 'taxes', 'currency', 'company', 'customer'])->first();
        
        if (!$invoice) {
            $this->markTestSkipped('No invoice found in database');
        }

        $result = $this->eInvoiceService->generate($invoice, 'UBL');
        
        $this->assertTrue($result['success'], 'E-invoice generation failed: '.($result['error'] ?? ''));
        
        $xml = $result['xml'];
        
        // Verify XML is well-formed
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<Invoice', $xml);
        
        // Extract totals from XML
        preg_match('/<cbc:PayableAmount[^>]*>([^<]+)<\/cbc:PayableAmount>/', $xml, $payableMatches);
        preg_match('/<cbc:TaxExclusiveAmount[^>]*>([^<]+)<\/cbc:TaxExclusiveAmount>/', $xml, $exclusiveMatches);
        preg_match('/<cbc:TaxAmount[^>]*>([^<]+)<\/cbc:TaxAmount>/', $xml, $taxMatches);
        
        $payableAmount = (float) ($payableMatches[1] ?? 0);
        $taxExclusiveAmount = (float) ($exclusiveMatches[1] ?? 0);
        $taxAmount = (float) ($taxMatches[1] ?? 0);
        
        // Calculate expected values from invoice
        $expectedSubtotal = ($invoice->sub_total ?? 0) / 100;
        $expectedDiscount = ($invoice->discount_val ?? 0) / 100;
        $expectedTax = ($invoice->tax ?? 0) / 100;
        $expectedTotal = ($invoice->total ?? 0) / 100;
        
        $expectedExclusive = round($expectedSubtotal - $expectedDiscount, 2);
        $expectedPayable = round($expectedExclusive + $expectedTax, 2);
        
        // Verify BR-CO-15: Total = Net + Tax
        $this->assertEqualsWithDelta(
            $expectedPayable,
            $payableAmount,
            0.01,
            "PayableAmount should be {$expectedPayable}, got {$payableAmount}"
        );
        
        $this->assertEqualsWithDelta(
            $expectedExclusive,
            $taxExclusiveAmount,
            0.01,
            "TaxExclusiveAmount should be {$expectedExclusive}, got {$taxExclusiveAmount}"
        );
        
        // Verify tax amount matches
        if ($expectedTax > 0) {
            $this->assertGreaterThan(0, $taxAmount, 'TaxAmount should be greater than 0');
        }
        
        // Verify BR-CO-15: PayableAmount = TaxExclusiveAmount + TaxAmount
        $calculatedPayable = round($taxExclusiveAmount + $taxAmount, 2);
        $this->assertEqualsWithDelta(
            $payableAmount,
            $calculatedPayable,
            0.01,
            "BR-CO-15: PayableAmount ({$payableAmount}) should equal TaxExclusiveAmount ({$taxExclusiveAmount}) + TaxAmount ({$taxAmount}) = {$calculatedPayable}"
        );
    }

    /**
     * Test all invoices in database
     */
    public function test_all_invoices_in_database(): void
    {
        $invoices = Invoice::with(['items', 'taxes', 'currency', 'company', 'customer'])
            ->whereNotNull('invoice_number')
            ->limit(10)
            ->get();
        
        if ($invoices->isEmpty()) {
            $this->markTestSkipped('No invoices found in database');
        }

        $errors = [];
        
        foreach ($invoices as $invoice) {
            try {
                $result = $this->eInvoiceService->generate($invoice, 'UBL');
                
                if (!$result['success']) {
                    $errors[] = "Invoice {$invoice->invoice_number}: Generation failed - ".($result['error'] ?? 'unknown');
                    continue;
                }
                
                $xml = $result['xml'];
                
                // Extract totals
                preg_match('/<cbc:PayableAmount[^>]*>([^<]+)<\/cbc:PayableAmount>/', $xml, $payableMatches);
                preg_match('/<cbc:TaxExclusiveAmount[^>]*>([^<]+)<\/cbc:TaxExclusiveAmount>/', $xml, $exclusiveMatches);
                preg_match('/<cbc:TaxAmount[^>]*>([^<]+)<\/cbc:TaxAmount>/', $xml, $taxMatches);
                
                $payableAmount = (float) ($payableMatches[1] ?? 0);
                $taxExclusiveAmount = (float) ($exclusiveMatches[1] ?? 0);
                $taxAmount = (float) ($taxMatches[1] ?? 0);
                
                // Calculate expected values
                $expectedSubtotal = ($invoice->sub_total ?? 0) / 100;
                $expectedDiscount = ($invoice->discount_val ?? 0) / 100;
                $expectedTax = ($invoice->tax ?? 0) / 100;
                
                $expectedExclusive = round($expectedSubtotal - $expectedDiscount, 2);
                $expectedPayable = round($expectedExclusive + $expectedTax, 2);
                
                // Verify BR-CO-15
                $calculatedPayable = round($taxExclusiveAmount + $taxAmount, 2);
                if (abs($payableAmount - $calculatedPayable) > 0.01) {
                    $errors[] = "Invoice {$invoice->invoice_number}: BR-CO-15 violation - PayableAmount ({$payableAmount}) != TaxExclusiveAmount ({$taxExclusiveAmount}) + TaxAmount ({$taxAmount}) = {$calculatedPayable}";
                }
                
                // Verify totals match expected
                if (abs($payableAmount - $expectedPayable) > 0.01) {
                    $errors[] = "Invoice {$invoice->invoice_number}: PayableAmount mismatch - Expected {$expectedPayable}, got {$payableAmount}";
                }
                
                if (abs($taxExclusiveAmount - $expectedExclusive) > 0.01) {
                    $errors[] = "Invoice {$invoice->invoice_number}: TaxExclusiveAmount mismatch - Expected {$expectedExclusive}, got {$taxExclusiveAmount}";
                }
                
            } catch (\Exception $e) {
                $errors[] = "Invoice {$invoice->invoice_number}: Exception - ".$e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $this->fail("Errors found in invoices:\n".implode("\n", $errors));
        }
        
        $this->assertTrue(true, "All {$invoices->count()} invoices passed validation");
    }
}
