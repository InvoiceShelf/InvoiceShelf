<?php

namespace App\Http\Controllers\V1\Admin\RecurringInvoice;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use Illuminate\Http\Request;

class RecurringInvoiceFrequencyController extends Controller
{
    public function __invoke(Request $request)
    {
        // The same date saving the schedule will store.
        $nextInvoiceAt = RecurringInvoice::getNextInvoiceDate(
            $request->frequency,
            RecurringInvoice::runCountsFrom($request->starts_at),
            CompanySetting::getSetting('time_zone', $request->header('company')) ?: null
        );

        return response()->json([
            'success' => true,
            'next_invoice_at' => $nextInvoiceAt,
        ]);
    }
}
