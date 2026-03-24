<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DownloadPaymentPdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(Payment $payment)
    {
        $path = storage_path('app/temp/payment/'.$payment->id.'.pdf');

        return response()->download($path);
    }
}
