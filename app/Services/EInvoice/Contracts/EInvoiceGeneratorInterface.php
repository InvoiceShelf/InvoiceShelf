<?php

namespace App\Services\EInvoice\Contracts;

use App\Models\Invoice;

interface EInvoiceGeneratorInterface
{
    /**
     * Generate e-invoice in the specified format
     *
     * @return array ['xml' => string, 'pdf' => string|null]
     */
    public function generate(Invoice $invoice, string $format = 'UBL'): array;

    /**
     * Get supported formats
     */
    public function getSupportedFormats(): array;

    /**
     * Validate invoice data for e-invoice compliance
     */
    public function validate(Invoice $invoice): array;
}
