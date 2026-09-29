<?php

namespace App\Platform\Notifications\Listeners;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Events\RecurringCostFailed;
use App\Domains\Purchases\Events\RecurringCostGenerated;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Sales\Events\EstimateViewed;
use App\Domains\Sales\Events\InvoiceViewed;
use App\Domains\Sales\Events\RecurringInvoiceFailed;
use App\Domains\Sales\Events\RecurringInvoiceGenerated;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Platform\Notifications\Application\NotificationCenter;
use App\Platform\Notifications\NotificationMessage;
use Illuminate\Events\Dispatcher;

/**
 * Turns what happened in the app into notices for the staff it concerns.
 */
class NotifyStaff
{
    public function __construct(private readonly NotificationCenter $center) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceViewed::class => 'invoiceViewed',
            EstimateViewed::class => 'estimateViewed',
            RecurringInvoiceGenerated::class => 'recurringInvoiceGenerated',
            RecurringInvoiceFailed::class => 'recurringInvoiceFailed',
            RecurringCostGenerated::class => 'recurringCostGenerated',
            RecurringCostFailed::class => 'recurringCostFailed',
        ];
    }

    public function invoiceViewed(InvoiceViewed $event): void
    {
        $invoice = Invoice::query()->with('customer')->find($event->invoiceId);

        if ($invoice === null) {
            return;
        }

        $this->customerActivity('notify_invoice_viewed', new NotificationMessage(
            type: 'invoice_viewed',
            companyId: $event->companyId,
            subject: $invoice,
            params: ['customer' => (string) $invoice->customer?->name, 'number' => (string) $invoice->invoice_number],
            url: "/admin/invoices/{$invoice->id}/view",
        ));
    }

    public function estimateViewed(EstimateViewed $event): void
    {
        $estimate = Estimate::query()->with('customer')->find($event->estimateId);

        if ($estimate === null) {
            return;
        }

        $this->customerActivity('notify_estimate_viewed', new NotificationMessage(
            type: 'estimate_viewed',
            companyId: $event->companyId,
            subject: $estimate,
            params: ['customer' => (string) $estimate->customer?->name, 'number' => (string) $estimate->estimate_number],
            url: "/admin/estimates/{$estimate->id}/view",
        ));
    }

    /**
     * Only when the schedule asks for it; a failure is always told.
     */
    public function recurringInvoiceGenerated(RecurringInvoiceGenerated $event): void
    {
        $schedule = RecurringInvoice::query()->with('customer')->find($event->scheduleId);
        $invoice = Invoice::query()->with('currency')->find($event->invoiceId);

        if ($schedule === null || $invoice === null || ! $schedule->notify_creator) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'recurring_invoice_generated',
            companyId: $event->companyId,
            subject: $invoice,
            params: [
                'customer' => (string) $schedule->customer?->name,
                'number' => (string) $invoice->invoice_number,
                'date' => (string) $invoice->formattedInvoiceDate,
                'amount' => $this->money($invoice->total, $invoice->currency),
            ],
            variant: $invoice->status === Invoice::STATUS_DRAFT ? 'draft' : 'sent',
            url: "/admin/invoices/{$invoice->id}/view",
        ), $this->creator($schedule->creator_id));
    }

    public function recurringInvoiceFailed(RecurringInvoiceFailed $event): void
    {
        $schedule = RecurringInvoice::query()->with('customer')->find($event->scheduleId);

        if ($schedule === null) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'recurring_invoice_failed',
            companyId: $event->companyId,
            subject: $schedule,
            params: ['customer' => (string) $schedule->customer?->name, 'reason' => "errors.{$event->reason}"],
            translate: ['reason'],
            variant: $event->reason === 'recurring_invoice_send_failed' ? 'send_failed' : null,
            url: "/admin/recurring-invoices/{$schedule->id}/view",
        ), $this->creator($schedule->creator_id));
    }

    /**
     * Only when the schedule asks for it; a failure is always told.
     */
    public function recurringCostGenerated(RecurringCostGenerated $event): void
    {
        $schedule = RecurringCost::query()->find($event->scheduleId);
        $record = match ($event->recordType) {
            'bill' => Bill::query()->with('currency')->find($event->recordId),
            'expense' => Expense::query()->with('currency')->find($event->recordId),
            default => null,
        };

        if ($schedule === null || $record === null || ! $schedule->notify_creator) {
            return;
        }

        $isBill = $record instanceof Bill;

        $this->center->send(new NotificationMessage(
            type: 'recurring_cost_generated',
            companyId: $event->companyId,
            subject: $record,
            params: [
                'name' => (string) $schedule->name,
                'number' => $isBill ? (string) $record->number : '',
                'date' => (string) ($isBill ? $record->document_date : $record->expense_date),
                'amount' => $this->money($isBill ? $record->total : $record->amount, $record->currency),
            ],
            variant: $isBill ? ($record->status === 'DRAFT' ? 'bill_draft' : 'bill') : 'expense',
            url: $isBill ? "/admin/bills/{$record->id}/view" : "/admin/expenses/{$record->id}/edit",
        ), $this->creator($schedule->creator_id));
    }

    public function recurringCostFailed(RecurringCostFailed $event): void
    {
        $schedule = RecurringCost::query()->find($event->scheduleId);

        if ($schedule === null) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'recurring_cost_failed',
            companyId: $event->companyId,
            subject: $schedule,
            params: ['name' => (string) $schedule->name, 'reason' => "errors.{$event->reason}"],
            translate: ['reason'],
            url: "/admin/recurring-costs/{$schedule->id}/view",
        ), $this->creator($schedule->creator_id));
    }

    /**
     * Customer activity goes to the members who may see the document, and
     * to the company's shared mailbox when the company switched that on.
     */
    private function customerActivity(string $mailboxSetting, NotificationMessage $message): void
    {
        $this->center->send($message);

        if (CompanySetting::getSetting($mailboxSetting, $message->companyId) !== 'YES') {
            return;
        }

        $mailbox = CompanySetting::getSetting('notification_email', $message->companyId);

        if (is_string($mailbox) && filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            $this->center->mailTo($mailbox, $message);
        }
    }

    private function creator(?int $id): ?User
    {
        return $id ? User::query()->find($id) : null;
    }

    private function money(mixed $amount, mixed $currency): string
    {
        return $currency ? trim(html_entity_decode(strip_tags(format_money_pdf($amount, $currency)))) : (string) $amount;
    }
}
