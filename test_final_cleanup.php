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

echo "Testing final cleanup and UBL-only functionality...\n\n";

try {
    // Get a real invoice from the database
    $invoice = Invoice::with(['items', 'taxes', 'company', 'customer'])->first();

    if (! $invoice) {
        echo "❌ No invoices found in database\n";
        exit(1);
    }

    echo "📄 Testing with invoice: {$invoice->invoice_number}\n\n";

    // Test UBL generation
    echo "🔧 Testing UBL generation...\n";
    $eInvoiceService = app(EInvoiceService::class);
    $result = $eInvoiceService->generate($invoice, 'UBL', false);

    if ($result['success']) {
        echo "✅ UBL generation successful!\n";
        echo '📄 XML length: '.strlen($result['xml'])." characters\n\n";

        // Test validation
        echo "🔍 Testing XML validation...\n";
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

        // Test unsupported format rejection
        echo "\n🔍 Testing unsupported format rejection...\n";
        try {
            $eInvoiceService->generate($invoice, 'CII', false);
            echo "❌ CII should be rejected but wasn't\n";
        } catch (\InvalidArgumentException $e) {
            echo "✅ CII correctly rejected: {$e->getMessage()}\n";
        }

        try {
            $eInvoiceService->generate($invoice, 'Factur-X', false);
            echo "❌ Factur-X should be rejected but wasn't\n";
        } catch (\InvalidArgumentException $e) {
            echo "✅ Factur-X correctly rejected: {$e->getMessage()}\n";
        }

        try {
            $eInvoiceService->generate($invoice, 'ZUGFeRD', false);
            echo "❌ ZUGFeRD should be rejected but wasn't\n";
        } catch (\InvalidArgumentException $e) {
            echo "✅ ZUGFeRD correctly rejected: {$e->getMessage()}\n";
        }

        // Test supported formats
        echo "\n🔍 Testing supported formats...\n";
        $supportedFormats = $eInvoiceService->getSupportedFormats();
        echo '📋 Supported formats: '.implode(', ', $supportedFormats)."\n";

        if (count($supportedFormats) === 1 && $supportedFormats[0] === 'UBL') {
            echo "✅ Only UBL format is supported\n";
        } else {
            echo "❌ Unexpected supported formats\n";
        }

        // Save XML for inspection
        file_put_contents('test_final_cleanup_output.xml', $result['xml']);
        echo "\n📄 XML saved to test_final_cleanup_output.xml for inspection\n";

    } else {
        echo "❌ UBL generation failed!\n";
        echo "🚨 Error: {$result['error']}\n";
    }

} catch (Exception $e) {
    echo '❌ Test failed with exception: '.$e->getMessage()."\n";
    echo '📍 File: '.$e->getFile().':'.$e->getLine()."\n";
    echo '📍 Trace: '.$e->getTraceAsString()."\n";
}

echo "\n🏁 Final cleanup test completed!\n";
