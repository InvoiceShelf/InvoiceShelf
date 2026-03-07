<?php

namespace App\Http\Controllers\V1\EInvoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EInvoiceController extends Controller
{
    public function __construct(
        private EInvoiceService $eInvoiceService
    ) {}

    /**
     * Generate e-invoice for the specified invoice
     */
    public function generate(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $request->validate([
            'format' => 'required|string|in:UBL',
            'async' => 'boolean',
        ]);

        $format = $request->input('format', 'UBL');
        $async = $request->input('async', true);

        try {
            // Generate using easybill/e-invoicing
            $result = $this->eInvoiceService->generate($invoice, $format, true);

            return response()->json([
                'message' => 'E-invoice generated successfully',
                'format' => $format,
                'files' => $result['saved_files'] ?? [],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate e-invoice',
            ], 500);
        }
    }

    /**
     * Get e-invoice file
     */
    public function download(Request $request, Invoice $invoice, string $format, string $type = 'xml'): Response
    {
        $this->authorize('view', $invoice);

        $request->validate([
            'type' => 'string|in:xml,pdf',
        ]);

        $type = $request->input('type', $type);

        try {
            $content = $this->eInvoiceService->getFromStorage($invoice, $format, $type);

            if (! $content) {
                return response()->json([
                    'error' => 'E-invoice file not found',
                ], 404);
            }

            $filename = $this->generateDownloadFilename($invoice, $format, $type);
            $mimeType = $type === 'xml' ? 'application/xml' : 'application/pdf';

            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve e-invoice file',
            ], 500);
        }
    }

    /**
     * Check if e-invoice exists
     */
    public function exists(Request $request, Invoice $invoice, string $format): JsonResponse
    {
        $this->authorize('view', $invoice);

        $request->validate([
            'type' => 'string|in:xml,pdf',
        ]);

        $type = $request->input('type', 'xml');

        $exists = $this->eInvoiceService->existsInStorage($invoice, $format, $type);

        return response()->json([
            'exists' => $exists,
            'format' => $format,
            'type' => $type,
        ]);
    }

    /**
     * Get supported formats
     */
    public function formats(): JsonResponse
    {
        $formats = $this->eInvoiceService->getSupportedFormats();

        return response()->json([
            'formats' => $formats,
        ]);
    }

    /**
     * Validate invoice for e-invoice compliance
     */
    public function validateInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $request->validate([
            'format' => 'required|string|in:UBL',
        ]);

        $format = $request->input('format');
        $errors = $this->eInvoiceService->validate($invoice, $format);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
            'format' => $format,
        ]);
    }

    /**
     * Delete e-invoice files
     */
    public function delete(Request $request, Invoice $invoice, string $format): JsonResponse
    {
        $this->authorize('update', $invoice);

        try {
            $deleted = $this->eInvoiceService->deleteFromStorage($invoice, $format);

            return response()->json([
                'message' => 'E-invoice files deleted successfully',
                'deleted' => $deleted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete e-invoice files',
            ], 500);
        }
    }

    /**
     * Generate download filename
     */
    private function generateDownloadFilename(Invoice $invoice, string $format, string $type): string
    {
        $safeFormat = strtolower(str_replace(['-', ' '], '_', $format));
        $safeNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $invoice->invoice_number);

        return "{$safeNumber}_{$safeFormat}.{$type}";
    }
}
