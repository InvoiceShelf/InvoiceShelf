<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Http\Requests\RecurrenceFrequencyRequest;
use App\Platform\Http\Controller;
use App\Support\Recurrence\Cadence;
use Illuminate\Http\JsonResponse;

/**
 * Previews when a schedule would run: the first run and the few after it.
 * Recurring invoices and recurring bills and expenses both ask for this.
 */
class RecurringInvoiceFrequencyController extends Controller
{
    /**
     * Read a cron expression and a start date, and answer with the runs they
     * produce, worked out in the company's time zone like the schedule
     * itself. Nothing is gated or written down; an expression or a date the
     * parser cannot read is a validation error.
     */
    public function __invoke(RecurrenceFrequencyRequest $request): JsonResponse
    {
        $timezone = CompanySetting::timeZone($request->header('company'));
        $upcoming = Cadence::upcoming(
            $request->validated('frequency'),
            $request->validated('starts_at'),
            $timezone,
        );

        return response()->json([
            'success' => true,
            'next_invoice_at' => $upcoming[0]->format('Y-m-d H:i:s'),
            'upcoming' => array_map(fn ($moment) => Cadence::localDate($moment, $timezone), $upcoming),
        ]);
    }
}
