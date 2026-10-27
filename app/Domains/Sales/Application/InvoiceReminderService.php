<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Events\InvoiceReminderSent;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceReminder;
use App\Platform\Mail\Contracts\MailConfigurator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Payment reminders: emails to customers about invoices they have not paid,
 * on the days the company chose around the due date, or sent by hand.
 *
 * A reminder goes out through the same path as the invoice itself (the
 * company's mail server, a fresh public link, the PDF when asked for), and
 * never changes the invoice's status. Each scheduled one is sent once.
 */
class InvoiceReminderService
{
    /**
     * How many days late a scheduled reminder may still go out, so an
     * install that was down catches up.
     */
    public const CATCH_UP_DAYS = 2;

    /**
     * A scheduled reminder whose email failed is tried again on a later run,
     * this many times in all, at least RETRY_AFTER_HOURS apart, while it is
     * still within the catch-up window.
     */
    public const MAX_ATTEMPTS = 3;

    public const RETRY_AFTER_HOURS = 6;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceEmailSender $emailSender,
        private readonly MailConfigurator $mailConfigurator,
        private readonly InvoicePaymentLinks $paymentLinks,
    ) {}

    /**
     * Send every scheduled reminder that is due now, across all companies.
     *
     * @return int how many were sent
     */
    public function sendDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $sent = 0;

        $companies = CompanySetting::query()
            ->where('option', 'reminders_enabled')
            ->where('value', 'YES')
            ->pluck('company_id');

        foreach ($companies as $companyId) {
            $sent += $this->sendDueFor((int) $companyId, $now);
        }

        return $sent;
    }

    /**
     * One company's reminders, once its local time has reached the send hour.
     */
    public function sendDueFor(int $companyId, CarbonImmutable $now): int
    {
        $settings = ReminderSettings::for($companyId);
        $local = $now->setTimezone(CompanySetting::timeZone($companyId));

        if (! $settings->enabled || $settings->offsets === [] || $local->hour < $settings->sendHour) {
            return 0;
        }

        $today = $local->startOfDay();
        $earliestDue = $today->subDays(max($settings->offsets) + self::CATCH_UP_DAYS)->toDateString();
        $latestDue = $today->subDays(min($settings->offsets))->toDateString();
        $sent = 0;

        $invoices = $this->remindable()
            ->where('invoices.company_id', $companyId)
            ->whereDate('due_date', '>=', $earliestDue)
            ->whereDate('due_date', '<=', $latestDue)
            ->with(['customer', 'reminders:id,invoice_id,offset_days,status,attempts,updated_at'])
            ->get();

        foreach ($invoices as $invoice) {
            $offset = $this->offsetDue($invoice, $settings->offsets, $today, $now);

            if ($offset !== null && $this->send($invoice, $settings, $offset, $now)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send a reminder now, whatever the schedule says.
     *
     * @throws ValidationException when the invoice is not one to remind about,
     *                             or the email could not be sent (the failure stays in the history)
     */
    public function sendNow(Invoice $invoice, ?User $by = null): InvoiceReminder
    {
        $this->ensure($this->isRemindable($invoice), 'invoice_reminder_not_due');
        $this->ensure((bool) $invoice->customer?->email, 'invoice_reminder_no_email');

        $settings = ReminderSettings::for((int) $invoice->company_id);
        $reminder = $invoice->reminders()->create([
            'company_id' => $invoice->company_id,
            'offset_days' => null,
            'status' => InvoiceReminder::STATUS_SENT,
            'sent_by' => $by?->id,
        ]);

        $this->ensure($this->deliver($invoice, $settings, $reminder), 'invoice_reminder_failed');

        return $reminder->refresh();
    }

    /**
     * The next scheduled reminder for an invoice, if one is still to come.
     *
     * @return array{date: string, offset: int}|null
     */
    public function next(Invoice $invoice, ?CarbonImmutable $now = null): ?array
    {
        $settings = ReminderSettings::for((int) $invoice->company_id);

        if (! $settings->enabled || ! $this->isRemindable($invoice) || $invoice->reminders_paused || $invoice->customer?->reminders_paused) {
            return null;
        }

        $today = ($now ?? CarbonImmutable::now())->setTimezone(CompanySetting::timeZone($invoice->company_id))->startOfDay();
        $due = CarbonImmutable::parse(substr((string) $invoice->due_date, 0, 10), $today->getTimezone());
        $sentOffsets = $invoice->reminders()->whereNotNull('offset_days')->get()
            ->reject(fn (InvoiceReminder $reminder): bool => $this->willRetry($reminder))
            ->pluck('offset_days')->all();
        $latestSent = $sentOffsets === [] ? null : max($sentOffsets);

        foreach ($settings->offsets as $offset) {
            $date = $due->addDays($offset);

            if (($latestSent === null || $offset > $latestSent) && $date->greaterThanOrEqualTo($today->subDays(self::CATCH_UP_DAYS))) {
                return ['date' => $date->toDateString(), 'offset' => $offset];
            }
        }

        return null;
    }

    /**
     * Whether an invoice is one a customer can be reminded about: sent, not
     * a credit note, and not yet paid.
     */
    public function isRemindable(Invoice $invoice): bool
    {
        return $invoice->type === Invoice::TYPE_INVOICE
            && in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED], true)
            && in_array($invoice->paid_status, [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID], true)
            && (int) $invoice->due_amount > 0
            && $invoice->due_date !== null;
    }

    /**
     * @return Builder<Invoice>
     */
    private function remindable(): Builder
    {
        return Invoice::query()
            ->where('type', Invoice::TYPE_INVOICE)
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED])
            ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_date')
            ->where('reminders_paused', false)
            ->whereHas('customer', fn (Builder $customer) => $customer->where('reminders_paused', false));
    }

    /**
     * The latest scheduled offset that has come round, when it is not too
     * late for it and it was not sent yet, or its email failed and it may be
     * tried again now. Earlier ones it passed over are not sent: a customer
     * gets one reminder at a time.
     *
     * @param  list<int>  $offsets
     */
    private function offsetDue(Invoice $invoice, array $offsets, CarbonImmutable $today, CarbonImmutable $now): ?int
    {
        $due = CarbonImmutable::parse(substr((string) $invoice->due_date, 0, 10), $today->getTimezone());
        $scheduled = $invoice->reminders->filter(fn (InvoiceReminder $reminder): bool => $reminder->offset_days !== null);
        $done = $scheduled->reject(fn (InvoiceReminder $reminder): bool => $this->willRetry($reminder))
            ->pluck('offset_days')->all();
        $retryable = $scheduled->filter(fn (InvoiceReminder $reminder): bool => $this->canRetryNow($reminder, $now))
            ->pluck('offset_days')->all();

        foreach (array_reverse($offsets) as $offset) {
            $date = $due->addDays($offset);

            if ($date->greaterThan($today)) {
                continue;
            }

            if (in_array($offset, $done, true) || $date->lessThan($today->subDays(self::CATCH_UP_DAYS))) {
                return null;
            }

            if ($done !== [] && max($done) > $offset) {
                return null;
            }

            $claimed = $scheduled->contains(fn (InvoiceReminder $reminder): bool => $reminder->offset_days === $offset);

            return ! $claimed || in_array($offset, $retryable, true) ? $offset : null;
        }

        return null;
    }

    /**
     * A failed reminder that has tries left.
     */
    private function willRetry(InvoiceReminder $reminder): bool
    {
        return $reminder->status === InvoiceReminder::STATUS_FAILED
            && $reminder->offset_days !== null
            && $reminder->attempts < self::MAX_ATTEMPTS;
    }

    private function canRetryNow(InvoiceReminder $reminder, CarbonImmutable $now): bool
    {
        return $this->willRetry($reminder)
            && $reminder->updated_at !== null
            && $reminder->updated_at->lessThanOrEqualTo($now->subHours(self::RETRY_AFTER_HOURS));
    }

    /**
     * Claim the offset, then send. The claim is what keeps two runs from
     * sending the same reminder.
     */
    private function send(Invoice $invoice, ReminderSettings $settings, int $offset, CarbonImmutable $now): bool
    {
        try {
            $reminder = $invoice->reminders()->create([
                'company_id' => $invoice->company_id,
                'offset_days' => $offset,
                'status' => InvoiceReminder::STATUS_SENT,
            ]);
        } catch (UniqueConstraintViolationException) {
            $reminder = $this->reclaim($invoice, $offset, $now);

            if ($reminder === null) {
                return false;
            }
        }

        if (! $invoice->customer?->email) {
            $reminder->update(['status' => InvoiceReminder::STATUS_SKIPPED, 'error' => 'invoice_reminder_no_email']);

            return false;
        }

        return $this->deliver($invoice, $settings, $reminder);
    }

    /**
     * Take a failed reminder back for another try. Only one run wins it, as
     * the update only matches while it is still failed.
     */
    private function reclaim(Invoice $invoice, int $offset, CarbonImmutable $now): ?InvoiceReminder
    {
        $query = InvoiceReminder::query()
            ->where('invoice_id', $invoice->id)
            ->where('offset_days', $offset)
            ->where('status', InvoiceReminder::STATUS_FAILED)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('updated_at', '<=', $now->utc()->subHours(self::RETRY_AFTER_HOURS));
        $id = (clone $query)->value('id');

        if ($id === null || $query->whereKey($id)->increment('attempts', 1, ['status' => InvoiceReminder::STATUS_SENT, 'error' => null]) !== 1) {
            return null;
        }

        return InvoiceReminder::query()->find($id);
    }

    /**
     * Email the reminder and note how it went. A mail server that is down
     * marks this reminder failed and leaves the others to go ahead.
     */
    private function deliver(Invoice $invoice, ReminderSettings $settings, InvoiceReminder $reminder): bool
    {
        try {
            $this->mailConfigurator->applyCompanyConfig($invoice->company_id);

            $this->emailSender->send([
                'from' => config('mail.from.address'),
                'to' => $invoice->customer->email,
                'subject' => $invoice->getEmailString($settings->subject),
                'body' => $invoice->getEmailString($settings->body),
                'invoice' => $invoice->toArray(),
                'customer' => $invoice->customer->toArray(),
                'company' => Company::query()->find($invoice->company_id),
                'attach' => ['data' => $settings->attachPdf ? $this->invoiceService->getPdfData($invoice) : null],
                'payment_link' => $this->paymentLinks->forInvoice($invoice),
            ], false);
        } catch (Throwable $error) {
            report($error);
            $reminder->update(['status' => InvoiceReminder::STATUS_FAILED, 'error' => mb_substr($error->getMessage(), 0, 250)]);

            return false;
        }

        $reminder->update(['email_log_id' => $invoice->emailLogs()->latest('id')->value('id')]);

        InvoiceReminderSent::dispatch((int) $invoice->id, (int) $invoice->company_id, $reminder->offset_days);

        return true;
    }

    /**
     * @throws ValidationException
     */
    private function ensure(bool $condition, string $code): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['invoice' => [$code]]);
        }
    }
}
