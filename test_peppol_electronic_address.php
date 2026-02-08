<?php

require_once 'vendor/autoload.php';

use App\Models\Invoice;
use App\Services\EInvoice\EInvoiceService;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing PEPPOL electronic address requirement...\n\n";

try {
    // Get a real invoice from the database
    $invoice = Invoice::with(['items', 'taxes', 'company', 'customer'])->first();

    if (! $invoice) {
        echo "❌ No invoices found in database\n";
        exit(1);
    }

    echo "📄 Testing with invoice: {$invoice->invoice_number}\n";
    echo "🏢 Company: {$invoice->company->name}\n";
    echo '📧 Company email: '.($invoice->company->email ?? 'NULL')."\n\n";

    // Test UBL generation
    echo "🔧 Testing UBL generation...\n";
    $eInvoiceService = app(EInvoiceService::class);
    $result = $eInvoiceService->generate($invoice, 'UBL', false);

    if ($result['success']) {
        echo "✅ UBL generation successful!\n";
        echo '📄 XML length: '.strlen($result['xml'])." characters\n\n";

        // Check for electronic address in XML
        echo "🔍 Checking for electronic address in XML...\n";
        $xml = $result['xml'];

        if (preg_match('/<cbc:EndpointID[^>]*>([^<]+)<\/cbc:EndpointID>/', $xml, $matches)) {
            echo "✅ Electronic address found: {$matches[1]}\n";
        } else {
            echo "❌ No electronic address found in XML\n";
        }

        // Check for scheme ID
        if (preg_match('/<cbc:EndpointID[^>]*schemeID="([^"]+)"[^>]*>([^<]+)<\/cbc:EndpointID>/', $xml, $matches)) {
            echo "✅ Electronic address scheme: {$matches[1]}\n";
            echo "✅ Electronic address value: {$matches[2]}\n";
        }

        // Test validation
        echo "\n🔍 Testing XML validation...\n";
        $validation = $eInvoiceService->validateXml($result['xml'], 'UBL');

        if ($validation['valid']) {
            echo "✅ XML validation passed!\n";
            echo '📊 Errors: '.count($validation['errors'])."\n";
            echo '⚠️  Warnings: '.count($validation['warnings'])."\n";
        } else {
            echo "❌ XML validation failed!\n";
            echo "🚨 Errors:\n";
            foreach ($validation['errors'] as $error) {
                echo "   - $error\n";
            }
        }

        // Save XML for inspection
        file_put_contents('test_peppol_electronic_address_output.xml', $result['xml']);
        echo "\n📄 XML saved to test_peppol_electronic_address_output.xml for inspection\n";

    } else {
        echo "❌ UBL generation failed!\n";
        echo "🚨 Error: {$result['error']}\n";
    }

} catch (Exception $e) {
    echo '❌ Test failed with exception: '.$e->getMessage()."\n";
    echo '📍 File: '.$e->getFile().':'.$e->getLine()."\n";
    echo '📍 Trace: '.$e->getTraceAsString()."\n";
}

echo "\n🏁 PEPPOL electronic address test completed!\n";
