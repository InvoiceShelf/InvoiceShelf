<?php

namespace App\Services\EInvoice;

use App\Models\EInvoice;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EInvoiceService
{
    private UBLService $ublService;

    public function __construct(UBLService $ublService)
    {
        $this->ublService = $ublService;
    }

    /**
     * Generate e-invoice in the specified format
     *
     * @throws \InvalidArgumentException
     */
    public function generate(Invoice $invoice, string $format = 'UBL', bool $saveToStorage = true): array
    {
        $format = $this->normalizeFormat($format);

        if ($format !== 'UBL') {
            throw new \InvalidArgumentException("Only UBL format is supported. Requested: {$format}");
        }

        // Generate the e-invoice using UBL service
        $result = $this->ublService->generate($invoice);

        if (! $result['success']) {
            throw new \InvalidArgumentException($result['error']);
        }

        if ($saveToStorage) {
            $result = $this->saveToStorage($invoice, $format, $result);
        }

        return $result;
    }

    /**
     * Get supported formats
     */
    public function getSupportedFormats(): array
    {
        return ['UBL'];
    }

    /**
     * Validate invoice for e-invoice compliance
     */
    public function validate(Invoice $invoice, string $format = 'UBL'): array
    {
        $format = $this->normalizeFormat($format);

        if ($format !== 'UBL') {
            return ["Unsupported format: {$format}"];
        }

        // Generate and validate the invoice
        $result = $this->ublService->generate($invoice);

        if (! $result['success']) {
            return [$result['error']];
        }

        return [];
    }

    /**
     * Validate e-invoice XML against EN16931 standard
     */
    public function validateXml(string $xml, string $format = 'UBL'): array
    {
        $format = $this->normalizeFormat($format);

        if ($format !== 'UBL') {
            return [
                'valid' => false,
                'errors' => ["Unsupported format: {$format}"],
                'warnings' => [],
            ];
        }

        return $this->ublService->validate($xml);
    }

    /**
     * Validate invoice and generated XML
     */
    public function validateInvoice(Invoice $invoice, string $format = 'UBL'): array
    {
        $format = $this->normalizeFormat($format);

        if ($format !== 'UBL') {
            return [
                'valid' => false,
                'errors' => ["Unsupported format: {$format}"],
                'warnings' => [],
            ];
        }

        // Generate the e-invoice
        $result = $this->ublService->generate($invoice);

        if (! $result['success']) {
            return [
                'valid' => false,
                'errors' => [$result['error']],
                'warnings' => [],
            ];
        }

        // Validate the generated XML
        return $this->ublService->validate($result['xml']);
    }

    /**
     * Get e-invoice file from storage
     *
     * @param  string  $type  ('xml' or 'pdf')
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
     */
    public function deleteFromStorage(Invoice $invoice, string $format): bool
    {
        $format = $this->normalizeFormat($format);
        $basePath = "e-invoices/{$invoice->company_id}/{$invoice->id}";

        $deleted = false;

        // Delete XML file
        $xmlPath = "{$basePath}/{$invoice->invoice_number}_{$format}.xml";
        if (Storage::exists($xmlPath)) {
            $deleted = Storage::delete($xmlPath) || $deleted;
        }

        // Also delete from database
        EInvoice::where('invoice_id', $invoice->id)
            ->where('format', $format)
            ->delete();

        return $deleted;
    }

    /**
     * Normalize format name
     */
    private function normalizeFormat(string $format): string
    {
        return strtoupper(trim($format));
    }

    /**
     * Save generated files to storage
     */
    private function saveToStorage(Invoice $invoice, string $format, array $result): array
    {
        $basePath = "e-invoices/{$invoice->company_id}/{$invoice->id}";

        $savedFiles = [];

        // Save XML
        if (! empty($result['xml'])) {
            $xmlFilename = $this->generateFilename($invoice, $format, 'xml');
            $xmlPath = "{$basePath}/{$xmlFilename}";
            Storage::put($xmlPath, $result['xml']);
            $savedFiles['xml'] = $xmlPath;
        }

        return array_merge($result, ['saved_files' => $savedFiles]);
    }

    /**
     * Generate filename for e-invoice file
     */
    private function generateFilename(Invoice $invoice, string $format, string $type): string
    {
        $safeFormat = Str::slug($format);
        $safeNumber = Str::slug($invoice->invoice_number);

        return "{$safeNumber}_{$safeFormat}.{$type}";
    }
}
