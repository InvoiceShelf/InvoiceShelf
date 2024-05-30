<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class SendPaymentPreviewController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Payment $payment)
    {
        $this->authorize('send payment', $payment);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $payment->sendPaymentData($request->all());
        $data['url'] = $payment->paymentPdfUrl;

        return $markdown->render('emails.send.payment', ['data' => $data]);
    }
}
