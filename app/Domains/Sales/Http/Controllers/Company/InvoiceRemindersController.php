<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Sales\Application\InvoiceReminderService;
use App\Domains\Sales\Application\ReminderSettings;
use App\Domains\Sales\Http\Requests\InvoiceReminderPauseRequest;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceReminder;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An invoice's payment reminders: what was sent, what comes next, pausing
 * them, and sending one now. Everything here is part of sending the
 * invoice, so it answers to the same gate.
 */
class InvoiceRemindersController extends Controller
{
    public function __construct(private readonly InvoiceReminderService $reminders) {}

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json(['data' => $this->present($invoice)]);
    }

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('send invoice', $invoice);

        $this->reminders->sendNow($invoice, $request->user());

        return response()->json(['data' => $this->present($invoice->refresh())]);
    }

    public function update(InvoiceReminderPauseRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('send invoice', $invoice);

        $invoice->update(['reminders_paused' => $request->boolean('paused')]);

        return response()->json(['data' => $this->present($invoice->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Invoice $invoice): array
    {
        $invoice->loadMissing('customer');

        return [
            'enabled' => ReminderSettings::for((int) $invoice->company_id)->enabled,
            'remindable' => $this->reminders->isRemindable($invoice),
            'paused' => (bool) $invoice->reminders_paused,
            'customer_paused' => (bool) $invoice->customer?->reminders_paused,
            'has_email' => (bool) $invoice->customer?->email,
            'next' => $this->reminders->next($invoice),
            'history' => $invoice->reminders()->latest('id')->limit(50)->get()
                ->map(fn (InvoiceReminder $reminder): array => [
                    'id' => $reminder->id,
                    'offset_days' => $reminder->offset_days,
                    'status' => $reminder->status,
                    'error' => $reminder->error,
                    'sent_by' => $reminder->sent_by,
                    'created_at' => $reminder->created_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
