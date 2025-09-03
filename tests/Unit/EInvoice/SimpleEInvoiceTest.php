<?php

namespace Tests\Unit\EInvoice;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleEInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_ubl_e_invoice()
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-1',
            'owner_id' => $user->id,
            'slug' => 'test-company',
        ]);

        $currency = Currency::create([
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'precision' => '2',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'company_name' => 'Test Company',
            'contact_name' => 'Test Contact',
            'prefix' => 'TC',
            'website' => 'https://test.com',
            'enable_portal' => true,
            'email' => 'test@customer.com',
            'phone' => '+1234567890',
            'company_id' => $company->id,
            'password' => bcrypt('secret'),
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 100.00,
            'total' => 120.00,
            'tax' => 20.00,
            'due_amount' => 120.00,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
            'notes' => 'Test invoice',
            'reference_number' => 'REF-001',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 2,
            'price' => 50.00,
            'total' => 100.00,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 20.00,
        ]);

        // Test e-invoice generation
        $service = new EInvoiceService;
        $result = $service->generate($invoice, 'UBL');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('xml', $result);
        $this->assertArrayHasKey('saved_files', $result);

        $xml = $result['xml'];
        $this->assertStringContainsString('<?xml version="1.0"?>', $xml);
        $this->assertStringContainsString('xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', $xml);
        $this->assertStringContainsString('INV-001', $xml);
        $this->assertStringContainsString('2024-01-15', $xml);
    }

    public function test_can_generate_cii_e_invoice()
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-2',
            'owner_id' => $user->id,
            'slug' => 'test-company-2',
        ]);

        $currency = Currency::create([
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'precision' => '2',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'company_name' => 'Test Company',
            'contact_name' => 'Test Contact',
            'prefix' => 'TC',
            'website' => 'https://test.com',
            'enable_portal' => true,
            'email' => 'test@customer.com',
            'phone' => '+1234567890',
            'company_id' => $company->id,
            'password' => bcrypt('secret'),
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-002',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 100.00,
            'total' => 120.00,
            'tax' => 20.00,
            'due_amount' => 120.00,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
            'notes' => 'Test invoice',
            'reference_number' => 'REF-002',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 2,
            'price' => 50.00,
            'total' => 100.00,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 20.00,
        ]);

        // Test e-invoice generation
        $service = new EInvoiceService;
        $result = $service->generate($invoice, 'CII');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('xml', $result);
        $this->assertArrayHasKey('saved_files', $result);

        $xml = $result['xml'];
        $this->assertStringContainsString('<?xml version="1.0"?>', $xml);
        $this->assertStringContainsString('xmlns="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"', $xml);
        $this->assertStringContainsString('INV-002', $xml);
    }

    public function test_can_validate_invoice()
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-3',
            'owner_id' => $user->id,
            'slug' => 'test-company-3',
        ]);

        $currency = Currency::create([
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'precision' => '2',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'company_name' => 'Test Company',
            'contact_name' => 'Test Contact',
            'prefix' => 'TC',
            'website' => 'https://test.com',
            'enable_portal' => true,
            'email' => 'test@customer.com',
            'phone' => '+1234567890',
            'company_id' => $company->id,
            'password' => bcrypt('secret'),
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-003',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 100.00,
            'total' => 120.00,
            'tax' => 20.00,
            'due_amount' => 120.00,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
            'notes' => 'Test invoice',
            'reference_number' => 'REF-003',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 2,
            'price' => 50.00,
            'total' => 100.00,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 20.00,
        ]);

        // Test validation
        $service = new EInvoiceService;
        $errors = $service->validate($invoice);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors); // No errors means valid
    }
}
