<?php

namespace App\Services\EInvoice;

use App\Models\Invoice;
use App\Services\EInvoice\Contracts\EInvoiceGeneratorInterface;
use App\Services\EInvoice\Generators\UBLGenerator;
use App\Services\EInvoice\Generators\CIIGenerator;
use App\Services\EInvoice\Generators\FacturXGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EInvoiceService
{
    private array $generators;

    public function __construct()
    {
        $this->generators = [
            'UBL' => new UBLGenerator(),
            'CII' => new CIIGenerator(),
            'Factur-X' => new FacturXGenerator(),
            'ZUGFeRD' => new FacturXGenerator(), // ZUGFeRD is essentially Factur-X
        ];
    }

    /**
     * Generate e-invoice in the specified format
     *
     * @param Invoice $invoice
     * @param string $format
     * @param bool $saveToStorage
     * @return array
     * @throws \InvalidArgumentException
     */
    public function generate(Invoice $invoice, string $format = 'UBL', bool $saveToStorage = true): array
    {
        $format = $this->normalizeFormat($format);
        
        if (!isset($this->generators[$format])) {
            throw new \InvalidArgumentException("Unsupported format: {$format}");
        }

        $generator = $this->generators[$format];
        
        // Validate invoice data
        $validationErrors = $generator->validate($invoice);
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Invoice validation failed: ' . implode(', ', $validationErrors));
        }

        // Generate the e-invoice
        $result = $generator->generate($invoice, $format);
        
        if ($saveToStorage) {
            $result = $this->saveToStorage($invoice, $format, $result);
        }

        return $result;
    }

    /**
     * Get supported formats
     *
     * @return array
     */
    public function getSupportedFormats(): array
    {
        $formats = [];
        foreach ($this->generators as $name => $generator) {
            $formats = array_merge($formats, $generator->getSupportedFormats());
        }
        return array_unique($formats);
    }

    /**
     * Validate invoice for e-invoice compliance
     *
     * @param Invoice $invoice
     * @param string $format
     * @return array
     */
    public function validate(Invoice $invoice, string $format = 'UBL'): array
    {
        $format = $this->normalizeFormat($format);
        
        if (!isset($this->generators[$format])) {
            return ["Unsupported format: {$format}"];
        }

        return $this->generators[$format]->validate($invoice);
    }

    /**
     * Get e-invoice file from storage
     *
     * @param Invoice $invoice
     * @param string $format
     * @param string $type ('xml' or 'pdf')
     * @return string|null
     */
    public function getFromStorage(Invoice $invoice, string $format, string $type = 'xml'): ?string
    {
        $format = $this->normalizeFormat($format);
        $filename = $this->generateFilename($invoice, $format, $type);
        $path = "e-invoices/{$invoice->company_id}/{$invoice->id}/{$filename}";
        
        if (Storage::exists($path)) {
            return Storage::get($path);
        }
        
        return null;
    }

    /**
     * Check if e-invoice exists in storage
     *
     * @param Invoice $invoice
     * @param string $format
     * @param string $type
     * @return bool
     */
    public function existsInStorage(Invoice $invoice, string $format, string $type = 'xml'): bool
    {
        $format = $this->normalizeFormat($format);
        $filename = $this->generateFilename($invoice, $format, $type);
        $path = "e-invoices/{$invoice->company_id}/{$invoice->id}/{$filename}";
        
        return Storage::exists($path);
    }

    /**
     * Delete e-invoice from storage
     *
     * @param Invoice $invoice
     * @param string $format
     * @return bool
     */
    public function deleteFromStorage(Invoice $invoice, string $format): bool
    {
        $format = $this->normalizeFormat($format);
        $path = "e-invoices/{$invoice->company_id}/{$invoice->id}";
        
        return Storage::deleteDirectory($path);
    }

    /**
     * Normalize format name
     *
     * @param string $format
     * @return string
     */
    private function normalizeFormat(string $format): string
    {
        $format = strtoupper(trim($format));
        
        // Handle common variations
        $mapping = [
            'FACTURX' => 'Factur-X',
            'FACTUR-X' => 'Factur-X',
            'ZUGFERD' => 'ZUGFeRD',
        ];
        
        return $mapping[$format] ?? $format;
    }

    /**
     * Save generated files to storage
     *
     * @param Invoice $invoice
     * @param string $format
     * @param array $result
     * @return array
     */
    private function saveToStorage(Invoice $invoice, string $format, array $result): array
    {
        $basePath = "e-invoices/{$invoice->company_id}/{$invoice->id}";
        
        $savedFiles = [];
        
        // Save XML
        if (!empty($result['xml'])) {
            $xmlFilename = $this->generateFilename($invoice, $format, 'xml');
            $xmlPath = "{$basePath}/{$xmlFilename}";
            Storage::put($xmlPath, $result['xml']);
            $savedFiles['xml'] = $xmlPath;
        }
        
        // Save PDF (if exists)
        if (!empty($result['pdf'])) {
            $pdfFilename = $this->generateFilename($invoice, $format, 'pdf');
            $pdfPath = "{$basePath}/{$pdfFilename}";
            Storage::put($pdfPath, $result['pdf']);
            $savedFiles['pdf'] = $pdfPath;
        }
        
        return array_merge($result, ['saved_files' => $savedFiles]);
    }

    /**
     * Generate filename for e-invoice file
     *
     * @param Invoice $invoice
     * @param string $format
     * @param string $type
     * @return string
     */
    private function generateFilename(Invoice $invoice, string $format, string $type): string
    {
        $safeFormat = Str::slug($format);
        $safeNumber = Str::slug($invoice->invoice_number);
        
        return "{$safeNumber}_{$safeFormat}.{$type}";
    }
}
