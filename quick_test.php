<?php

require_once 'vendor/autoload.php';

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\EInvoice\EInvoiceService;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create test data exactly like in the test
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
$result = $service->generate($invoice, 'UBL', false);

if ($result['success']) {
    $xml = $result['xml'];

    // Look for tax category
    if (preg_match('/<cac:ClassifiedTaxCategory>.*?<cbc:ID>([^<]+)<\/cbc:ID>.*?<\/cac:ClassifiedTaxCategory>/s', $xml, $matches)) {
        echo "Tax category found: {$matches[1]}\n";
    } else {
        echo "No tax category found\n";
    }

    // Look for tax rate
    if (preg_match('/<cbc:Percent>([^<]+)<\/cbc:Percent>/', $xml, $matches)) {
        echo "Tax rate found: {$matches[1]}%\n";
    } else {
        echo "No tax rate found\n";
    }

    // Save XML for inspection
    file_put_contents('quick_test_output.xml', $xml);
    echo "XML saved to quick_test_output.xml\n";

} else {
    echo "❌ UBL generation failed: {$result['error']}\n";
}
