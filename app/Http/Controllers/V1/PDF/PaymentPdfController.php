<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentPdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request, Payment $payment)
    {
        if ($request->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
