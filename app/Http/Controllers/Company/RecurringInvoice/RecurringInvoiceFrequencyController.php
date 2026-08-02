<?php

namespace App\Http\Controllers\Company\RecurringInvoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringInvoiceFrequencyRequest;
use App\Services\Document\RecurringInvoiceScheduleService;

class RecurringInvoiceFrequencyController extends Controller
{
    public function __invoke(RecurringInvoiceFrequencyRequest $request, RecurringInvoiceScheduleService $schedule)
    {
        $nextInvoiceAt = $schedule->firstFutureOccurrence($request->frequency, $request->starts_at, (int) $request->header('company'))
            ->format('Y-m-d H:i:s');

        return response()->json([
            'success' => true,
            'next_invoice_at' => $nextInvoiceAt,
        ]);
    }
}
