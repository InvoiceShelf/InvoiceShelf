<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class DocumentPdfController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly EstimateService $estimateService,
    ) {}

    public function invoice(Request $request, Invoice $invoice)
    {
        if ($request->has('preview')) {
            return $this->invoiceService->getPdfData($invoice);
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }

    public function estimate(Request $request, Estimate $estimate)
    {
        if ($request->has('preview')) {
            return $this->estimateService->getPdfData($estimate);
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    public function payment(Request $request, Payment $payment)
    {
        if ($request->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
