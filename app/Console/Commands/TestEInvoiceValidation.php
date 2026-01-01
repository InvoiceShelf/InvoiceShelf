<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestEInvoiceValidation extends Command
{
    protected $signature = 'e-invoice:test-validation {invoice_id} {--format=UBL}';

    protected $description = 'Test e-invoice validation against European validation service';

    public function handle(EInvoiceService $eInvoiceService)
    {
        $invoiceId = $this->argument('invoice_id');
        $format = $this->option('format');

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            $this->error("Invoice with ID {$invoiceId} not found");

            return 1;
        }

        $this->info("Testing e-invoice validation for invoice {$invoice->invoice_number} in format {$format}");

        try {
            // Generate the e-invoice
            $this->info('Generating e-invoice...');
            $result = $eInvoiceService->generate($invoice, $format);

            if (empty($result['xml'])) {
                $this->error('Failed to generate e-invoice XML');

                return 1;
            }

            $this->info('E-invoice generated successfully');

            // Test local validation
            $this->info('Running local validation...');
            $localValidation = $eInvoiceService->validateInvoice($invoice, $format);

            if ($localValidation['valid']) {
                $this->info('✅ Local validation passed');
            } else {
                $this->error('❌ Local validation failed:');
                foreach ($localValidation['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }

            if (! empty($localValidation['warnings'])) {
                $this->warn('⚠️  Local validation warnings:');
                foreach ($localValidation['warnings'] as $warning) {
                    $this->warn("  - {$warning}");
                }
            }

            // Test European validation service
            $this->info('Testing with European validation service...');
            $this->testEuropeanValidation($result['xml'], $format);

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function testEuropeanValidation(string $xml, string $format)
    {
        try {
            // Test the European validation service
            $response = Http::timeout(30)->post('https://www.itb.ec.europa.eu/vitb/rest/validate', [
                'xml' => $xml,
                'format' => $this->mapFormatToApi($format),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info('✅ European validation service response received');

                if ($data['valid'] ?? false) {
                    $this->info('✅ European validation passed');
                } else {
                    $this->error('❌ European validation failed');
                    if (isset($data['errors'])) {
                        foreach ($data['errors'] as $error) {
                            $this->error("  - {$error}");
                        }
                    }
                }

                if (isset($data['warnings']) && ! empty($data['warnings'])) {
                    $this->warn('⚠️  European validation warnings:');
                    foreach ($data['warnings'] as $warning) {
                        $this->warn("  - {$warning}");
                    }
                }
            } else {
                $this->warn("European validation service returned status: {$response->status()}");
                $this->warn("Response: {$response->body()}");
            }

        } catch (\Exception $e) {
            $this->warn("Could not connect to European validation service: {$e->getMessage()}");
            $this->info('This is normal if the service is temporarily unavailable');
        }
    }

    private function mapFormatToApi(string $format): string
    {
        return match ($format) {
            'UBL' => 'UBL Invoice XML - release 1.3.14.2',
            default => 'UBL Invoice XML - release 1.3.14.2',
        };
    }
}
