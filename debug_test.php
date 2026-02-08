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

echo "Debugging test amounts...\n\n";

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

echo "Invoice data:\n";
echo "  Total: {$invoice->total} (should be 120.00€)\n";
echo "  Sub Total: {$invoice->sub_total} (should be 100.00€)\n";
echo "  Tax: {$invoice->tax} (should be 20.00€)\n\n";

// Test e-invoice generation
$service = app(EInvoiceService::class);
$result = $service->generate($invoice, 'UBL', false);

if ($result['success']) {
    echo "✅ UBL generation successful!\n";

    $xml = $result['xml'];

    // Look for monetary amounts
    echo "\nMonetary amounts in XML:\n";
    if (preg_match_all('/<cbc:(?:PayableAmount|TaxExclusiveAmount|TaxInclusiveAmount|PriceAmount|LineExtensionAmount)[^>]*>([0-9.]+)<\/cbc:(?:PayableAmount|TaxExclusiveAmount|TaxInclusiveAmount|PriceAmount|LineExtensionAmount)>/', $xml, $matches)) {
        foreach ($matches[1] as $amount) {
            echo "  - $amount\n";
        }
    }

    // Save XML for inspection
    file_put_contents('debug_test_output.xml', $xml);
    echo "\n📄 XML saved to debug_test_output.xml\n";

} else {
    echo "❌ UBL generation failed: {$result['error']}\n";
}
