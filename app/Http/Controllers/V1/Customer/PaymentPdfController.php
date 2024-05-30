<?php

namespace App\Http\Controllers\V1\Customer;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\EmailLog;
use App\Models\Payment;

class PaymentPdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        if (! $emailLog->isExpired()) {
            return $emailLog->mailable->getGeneratedPDFOrStream('payment');
        }

        abort(403, 'Link Expired.');
    }

    public function getPayment(EmailLog $emailLog)
    {
        $payment = Payment::find($emailLog->mailable_id);

        return new PaymentResource($payment);
    }
}
