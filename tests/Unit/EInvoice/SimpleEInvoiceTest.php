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
            'sub_total' => 10000, // 100.00€ in cents
            'total' => 12000, // 120.00€ in cents
            'tax' => 2000, // 20.00€ in cents
            'due_amount' => 12000, // 120.00€ in cents
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
            'price' => 5000, // 50.00€ in cents
            'total' => 10000, // 100.00€ in cents
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 2000, // 20.00€ in cents
        ]);

        // Test e-invoice generation
        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('xml', $result);
        $this->assertArrayHasKey('saved_files', $result);

        $xml = $result['xml'];
        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', $xml);
        $this->assertStringContainsString('INV-001', $xml);
        $this->assertStringContainsString('2024-01-15', $xml);

        // Test amount conversion (cents to decimal)
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="EUR">100</cbc:PayableAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxExclusiveAmount currencyID="EUR">100</cbc:TaxExclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:PriceAmount currencyID="EUR">50</cbc:PriceAmount>', $xml);

        // Test electronic address (should use user email since company has no email)
        $this->assertStringContainsString('<cbc:EndpointID schemeID="EM">', $xml);

        // Test tax category (should be 'Z' for zero rate since no tax type is defined)
        $this->assertStringContainsString('<cbc:ID>Z</cbc:ID>', $xml);
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
            'sub_total' => 10000, // 100.00€ in cents
            'total' => 12000, // 120.00€ in cents
            'tax' => 2000, // 20.00€ in cents
            'due_amount' => 12000, // 120.00€ in cents
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
            'price' => 5000, // 50.00€ in cents
            'total' => 10000, // 100.00€ in cents
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 2000, // 20.00€ in cents
        ]);

        // Test validation
        $service = app(EInvoiceService::class);
        $errors = $service->validate($invoice);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors); // No errors means valid
    }

    public function test_rejects_unsupported_formats()
    {
        // Create minimal test data
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-4',
            'owner_id' => $user->id,
            'slug' => 'test-company-4',
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
            'company_id' => $company->id,
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-004',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 10000,
            'total' => 12000,
            'tax' => 2000,
            'due_amount' => 12000,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        $service = app(EInvoiceService::class);

        // Test that unsupported formats are rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only UBL format is supported. Requested: CII');
        $service->generate($invoice, 'CII');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only UBL format is supported. Requested: FACTUR-X');
        $service->generate($invoice, 'Factur-X');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only UBL format is supported. Requested: ZUGFERD');
        $service->generate($invoice, 'ZUGFeRD');
    }

    public function test_supports_only_ubl_format()
    {
        $service = app(EInvoiceService::class);
        $supportedFormats = $service->getSupportedFormats();

        $this->assertIsArray($supportedFormats);
        $this->assertCount(1, $supportedFormats);
        $this->assertContains('UBL', $supportedFormats);
    }

    public function test_electronic_address_fallback_to_user_email()
    {
        // Create user with email
        $user = User::factory()->create(['email' => 'user@test.com']);

        // Create company without email
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-5',
            'owner_id' => $user->id,
            'slug' => 'test-company-5',
            'email' => null, // No company email
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
            'company_id' => $company->id,
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-005',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 10000,
            'total' => 12000,
            'tax' => 2000,
            'due_amount' => 12000,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        // Add invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Test Product',
            'description' => 'Test Description',
            'quantity' => 1,
            'price' => 10000,
            'total' => 10000,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 2000,
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Should contain user email in electronic address
        $this->assertStringContainsString('<cbc:EndpointID schemeID="EM">user@test.com</cbc:EndpointID>', $xml);
    }

    public function test_tax_category_mapping()
    {
        // Create test data
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Test Company',
            'unique_hash' => 'test-hash-6',
            'owner_id' => $user->id,
            'slug' => 'test-company-6',
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
            'company_id' => $company->id,
            'currency_id' => $currency->id,
        ]);

        // Test zero-rated tax (0%)
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-006',
            'invoice_date' => '2024-01-15',
            'due_date' => '2024-02-15',
            'sub_total' => 10000,
            'total' => 10000, // No tax
            'tax' => 0, // 0% tax
            'due_amount' => 10000,
            'status' => 'DRAFT',
            'paid_status' => 'UNPAID',
            'tax_per_item' => 'YES',
            'discount_per_item' => 'NO',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'name' => 'Zero Tax Product',
            'description' => 'Product with 0% tax',
            'quantity' => 1,
            'price' => 10000,
            'total' => 10000,
            'discount_type' => 'FIXED',
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0, // 0% tax
        ]);

        $service = app(EInvoiceService::class);
        $result = $service->generate($invoice, 'UBL');

        $this->assertTrue($result['success']);
        $xml = $result['xml'];

        // Should contain 'Z' for zero-rated tax
        $this->assertStringContainsString('<cbc:ID>Z</cbc:ID>', $xml);
    }
}
