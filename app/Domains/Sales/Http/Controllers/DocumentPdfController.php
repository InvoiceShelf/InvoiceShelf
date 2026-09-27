<?php

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Application\QuoteService;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\Quote;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Http\DocumentPdfAccess;
use Illuminate\Http\Request;

class DocumentPdfController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly EstimateService $estimateService,
        private readonly QuoteService $quoteService,
    ) {}

    public function invoice(Request $request, Invoice $invoice)
    {
        DocumentPdfAccess::authorize($invoice);

        if ($request->exists('preview')) {
            return $this->invoiceService->getPdfData($invoice);
        }

        $pdf = $invoice->getGeneratedPDFOrStream('invoice');

        return $pdf;
    }

    public function estimate(Request $request, Estimate $estimate)
    {
        DocumentPdfAccess::authorize($estimate);

        if ($request->exists('preview')) {
            return $this->estimateService->getPdfData($estimate);
        }

        $pdf = $estimate->getGeneratedPDFOrStream('estimate');

        return $pdf;
    }

    public function quote(Request $request, Quote $quote)
    {
        DocumentPdfAccess::authorize($quote);

        if ($request->exists('preview')) {
            return $this->quoteService->getPdfData($quote);
        }

        $pdf = $quote->getGeneratedPDFOrStream('quote');

        return $pdf;
    }
}
