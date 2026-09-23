<?php

namespace App\Domains\Receivables\Http\Controllers;

use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Http\DocumentPdfAccess;
use Illuminate\Http\Request;

/**
 * Serves the receipt behind a payment's hash, to the customer it was issued
 * to or a member of the company who may view it.
 *
 * Asking for a preview short-circuits to the view data the template is
 * rendered from, so the layout can be looked at without a PDF engine in
 * the loop; every other caller gets the file itself.
 */
class PaymentPdfController extends Controller
{
    public function __construct(
        private readonly PaymentPdfDataProvider $paymentPdfDataProvider,
    ) {}

    public function __invoke(Request $request, Payment $payment): mixed
    {
        DocumentPdfAccess::authorize($payment);

        if ($request->has('preview')) {
            return $this->paymentPdfDataProvider->getPdfData($payment);
        }

        $receipt = $payment->getGeneratedPDFOrStream('payment');

        return $receipt;
    }
}
