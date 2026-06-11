<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\EmailLog;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentPdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $payment = $emailLog->mailable;
        abort_unless($payment instanceof Payment, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return $payment->getGeneratedPDFOrStream('payment');
    }

    public function getPayment(EmailLog $emailLog)
    {
        $payment = $emailLog->mailable;
        abort_unless($payment instanceof Payment, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return new PaymentResource($payment);
    }
}
