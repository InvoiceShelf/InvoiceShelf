<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DownloadInvoicePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(Invoice $invoice)
    {
        $path = storage_path('app/temp/invoice/'.$invoice->id.'.pdf');

        return response()->download($path);
    }
}
