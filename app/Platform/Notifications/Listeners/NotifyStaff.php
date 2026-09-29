<?php

namespace App\Platform\Notifications\Listeners;

use App\Domains\Accounts\Events\InvitationAnswered;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Events\BillBecameOverdue;
use App\Domains\Purchases\Events\BillDueSoon;
use App\Domains\Purchases\Events\RecurringCostFailed;
use App\Domains\Purchases\Events\RecurringCostGenerated;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Receivables\Events\PaymentRecorded;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Events\EstimateAnswered;
use App\Domains\Sales\Events\EstimateViewed;
use App\Domains\Sales\Events\InvoiceBecameOverdue;
use App\Domains\Sales\Events\InvoicePaid;
use App\Domains\Sales\Events\InvoiceViewed;
use App\Domains\Sales\Events\RecurringInvoiceFailed;
use App\Domains\Sales\Events\RecurringInvoiceGenerated;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Platform\Mcp\Events\McpConnectionBound;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Modules\Events\ModuleIncompatible;
use App\Platform\Notifications\Application\NotificationCenter;
use App\Platform\Notifications\NotificationMessage;
use App\Platform\Operations\Managed\ManagedMode;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Backup\Events\BackupHasFailed;

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
            EstimateAnswered::class => 'estimateAnswered',
            PaymentRecorded::class => 'paymentRecorded',
            InvoicePaid::class => 'invoicePaid',
            InvoiceBecameOverdue::class => 'invoiceBecameOverdue',
            BillDueSoon::class => 'billDueSoon',
            BillBecameOverdue::class => 'billBecameOverdue',
            InvitationAnswered::class => 'invitationAnswered',
            McpConnectionBound::class => 'mcpConnectionBound',
            ModuleIncompatible::class => 'moduleIncompatible',
            BackupHasFailed::class => 'backupFailed',
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

    public function estimateAnswered(EstimateAnswered $event): void
    {
        $estimate = Estimate::query()->with('customer')->find($event->estimateId);

        if ($estimate === null) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: $event->status === Estimate::STATUS_ACCEPTED ? 'estimate_accepted' : 'estimate_rejected',
            companyId: $event->companyId,
            subject: $estimate,
            params: ['customer' => (string) $estimate->customer?->name, 'number' => (string) $estimate->estimate_number],
            url: "/admin/estimates/{$estimate->id}/view",
        ));
    }

    /**
     * Whoever recorded it is not told about their own work.
     */
    public function paymentRecorded(PaymentRecorded $event): void
    {
        $payment = Payment::query()->with(['customer', 'currency'])->find($event->paymentId);

        if ($payment === null) {
            return;
        }

        $actor = $event->actorId ? User::query()->find($event->actorId) : null;

        $this->center->send(new NotificationMessage(
            type: 'payment_received',
            companyId: $event->companyId,
            subject: $payment,
            params: [
                'customer' => (string) $payment->customer?->name,
                'number' => (string) $payment->payment_number,
                'amount' => $this->money($payment->amount, $payment->currency),
                'member' => (string) $actor?->name,
            ],
            variant: $actor ? 'recorded' : 'online',
            url: "/admin/payments/{$payment->id}/view",
        ), except: $actor ? [(int) $actor->id] : []);
    }

    public function invoicePaid(InvoicePaid $event): void
    {
        $invoice = Invoice::query()->with(['customer', 'currency'])->find($event->invoiceId);

        if ($invoice === null) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'invoice_paid',
            companyId: $event->companyId,
            subject: $invoice,
            params: [
                'customer' => (string) $invoice->customer?->name,
                'number' => (string) $invoice->invoice_number,
                'amount' => $this->money($invoice->total, $invoice->currency),
            ],
            url: "/admin/invoices/{$invoice->id}/view",
        ));
    }

    public function invoiceBecameOverdue(InvoiceBecameOverdue $event): void
    {
        $invoice = Invoice::query()->with(['customer', 'currency'])->find($event->invoiceId);

        if ($invoice === null) {
            return;
        }

        $this->center->sendOnce(new NotificationMessage(
            type: 'invoice_overdue',
            companyId: $event->companyId,
            subject: $invoice,
            params: [
                'customer' => (string) $invoice->customer?->name,
                'number' => (string) $invoice->invoice_number,
                'amount' => $this->money($invoice->due_amount, $invoice->currency),
                'date' => (string) $invoice->formattedDueDate,
            ],
            url: "/admin/invoices/{$invoice->id}/view",
        ));
    }

    public function billDueSoon(BillDueSoon $event): void
    {
        $this->bill($event->billId, $event->companyId, 'bill_due_soon');
    }

    public function billBecameOverdue(BillBecameOverdue $event): void
    {
        $this->bill($event->billId, $event->companyId, 'bill_overdue');
    }

    /**
     * Told to whoever sent the invitation.
     */
    public function invitationAnswered(InvitationAnswered $event): void
    {
        $invitation = CompanyInvitation::query()->with(['company', 'invitedBy'])->find($event->invitationId);

        if ($invitation === null || $invitation->invitedBy === null) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: $event->status === CompanyInvitation::STATUS_ACCEPTED ? 'invitation_accepted' : 'invitation_declined',
            companyId: $event->companyId,
            params: ['email' => (string) $invitation->email, 'company' => (string) $invitation->company?->name],
            url: '/admin/members',
        ), $invitation->invitedBy);
    }

    /**
     * Told to the person, in case it was not them, and to the company's
     * owner, whose data the app can now read.
     */
    public function mcpConnectionBound(McpConnectionBound $event): void
    {
        $connection = McpConnection::query()->find($event->connectionId);
        $user = User::query()->find($event->userId);
        $company = Company::query()->find($event->companyId);

        if ($connection === null || $user === null || $company === null) {
            return;
        }

        $message = new NotificationMessage(
            type: 'ai_connection_added',
            companyId: $event->companyId,
            params: ['app' => (string) $connection->client_name, 'member' => (string) $user->name, 'company' => (string) $company->name],
            url: '/admin/account-settings/connected-apps',
        );

        $this->center->send($message, $user);

        $owner = $company->owner_id ? User::query()->find($company->owner_id) : null;

        if ($owner !== null && (int) $owner->id !== (int) $user->id) {
            $this->center->send(new NotificationMessage(
                type: 'ai_connection_added',
                companyId: $event->companyId,
                params: $message->params,
                variant: 'member',
                url: '/admin/members',
            ), $owner);
        }
    }

    /**
     * Runs at boot, possibly before this release's migrations: nothing is
     * sent until the notifications table has its company column.
     */
    public function moduleIncompatible(ModuleIncompatible $event): void
    {
        if (! Schema::hasColumn('notifications', 'company_id')) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'module_disabled',
            companyId: null,
            params: ['module' => $event->module, 'problems' => $event->problems],
            url: '/admin/administration/modules',
        ));
    }

    /**
     * Not on a managed install, where the provider keeps the backups.
     */
    public function backupFailed(BackupHasFailed $event): void
    {
        if (ManagedMode::enabled()) {
            return;
        }

        $this->center->send(new NotificationMessage(
            type: 'backup_failed',
            companyId: null,
            params: ['disk' => (string) $event->diskName, 'error' => Str::limit($event->exception->getMessage(), 200)],
            url: '/admin/administration/settings/backup',
        ));
    }

    private function bill(int $billId, int $companyId, string $type): void
    {
        $bill = Bill::query()->with(['supplier', 'currency'])->find($billId);

        if ($bill === null) {
            return;
        }

        $this->center->sendOnce(new NotificationMessage(
            type: $type,
            companyId: $companyId,
            subject: $bill,
            params: [
                'supplier' => (string) $bill->supplier?->name,
                'number' => (string) $bill->number,
                'amount' => $this->money($bill->due_amount, $bill->currency),
                'date' => (string) $bill->due_date,
            ],
            url: "/admin/bills/{$bill->id}/view",
        ));
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
