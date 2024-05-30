<?php

namespace App\Http\Controllers\V1\Admin\RecurringInvoice;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\RecurringInvoice;

class RecurringInvoiceFrequencyController extends Controller
{
    public function __invoke(Request $request)
    {
        $nextInvoiceAt = RecurringInvoice::getNextInvoiceDate($request->frequency, $request->starts_at);

        return response()->json([
            'success' => true,
            'next_invoice_at' => $nextInvoiceAt,
        ]);
    }
}
