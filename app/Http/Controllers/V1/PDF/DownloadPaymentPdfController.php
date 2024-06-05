<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class DownloadPaymentPdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Payment $payment)
    {
        $path = storage_path('app/temp/payment/'.$payment->id.'.pdf');

        return response()->download($path);
    }
}
