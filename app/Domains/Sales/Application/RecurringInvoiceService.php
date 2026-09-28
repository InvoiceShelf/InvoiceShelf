<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Support\MoneyConversion;
use App\Support\PublicToken;
use App\Support\Recurrence\Cadence;
use App\Support\Recurrence\RecurrenceRunner;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringInvoiceService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly InvoiceService $invoiceService,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly DocumentExchangeRateRecorder $exchangeRateRecorder,
        private readonly RecurrenceRunner $runner,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>|null  $taxes
     */
    public function create(
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): RecurringInvoice {
        $recurringInvoice = RecurringInvoice::create($attributes);

        $companyCurrency = CompanySetting::getSetting('currency', $recurringInvoice->company_id);

        if ((string) $recurringInvoice['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($recurringInvoice);
        }

        $this->documentItemService->createItems($recurringInvoice, $items);

        if ($taxes) {
            $this->documentItemService->createTaxes($recurringInvoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($recurringInvoice, $customFields);
        }

        return $recurringInvoice;
    }

    /**
     * Save changes to a schedule.
     *
     * The next run stays where it was unless the frequency or the start date
     * changed: the form sends one counted from the start date, and taking it
     * would bill the latest period again. A changed cadence, or a paused
     * schedule made active once its next run has gone by, carries on from
     * now (or from a start date still ahead).
     */
    public function update(
        RecurringInvoice $recurringInvoice,
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): RecurringInvoice {
        $cadenceChanged = $this->cadenceChanged($recurringInvoice, $attributes);
        $resumed = $recurringInvoice->status !== RecurringInvoice::ACTIVE
            && ($attributes['status'] ?? $recurringInvoice->status) === RecurringInvoice::ACTIVE;

        $recurringInvoice->update(Arr::except($attributes, ['next_invoice_at']));

        $overdue = $recurringInvoice->next_invoice_at === null
            || Carbon::parse($recurringInvoice->next_invoice_at)->isPast();

        if ($cadenceChanged || ($resumed && $overdue)) {
            $recurringInvoice->updateNextInvoiceDate();
        }

        $companyCurrency = CompanySetting::getSetting('currency', $recurringInvoice->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->exchangeRateRecorder->record($recurringInvoice);
        }

        // Answers to item-level custom fields have no cascade of their own,
        // so they are cleared row by row before the items are replaced.
        foreach ($recurringInvoice->items as $lineItem) {
            foreach ($lineItem->fields()->get() as $answer) {
                $answer->delete();
            }
        }

        $recurringInvoice->items()->delete();
        $this->documentItemService->createItems($recurringInvoice, $items);

        $recurringInvoice->taxes()->delete();
        if ($taxes) {
            $this->documentItemService->createTaxes($recurringInvoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($recurringInvoice, $customFields);
        }

        return $recurringInvoice;
    }

    /**
     * Whether a save changes when the schedule runs.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function cadenceChanged(RecurringInvoice $recurringInvoice, array $attributes): bool
    {
        if (array_key_exists('frequency', $attributes) && $attributes['frequency'] !== $recurringInvoice->frequency) {
            return true;
        }

        if (! array_key_exists('starts_at', $attributes)) {
            return false;
        }

        return $recurringInvoice->starts_at === null
            || ! Carbon::parse($attributes['starts_at'])->equalTo(Carbon::parse($recurringInvoice->starts_at));
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $recurringInvoice = RecurringInvoice::find($id);

            // Invoices already generated outlive their template; all they lose
            // is the link back to it.
            $generated = $recurringInvoice->invoices();

            if ($generated->exists()) {
                $generated->update(['recurring_invoice_id' => null]);
            }

            $lineItems = $recurringInvoice->items();

            if ($lineItems->exists()) {
                // Same reason as in update(): a bulk delete never reaches the
                // per-row hook that would clear each line's answers.
                foreach ($recurringInvoice->items as $lineItem) {
                    foreach ($lineItem->fields()->get() as $answer) {
                        $answer->delete();
                    }
                }

                $lineItems->delete();
            }

            if ($recurringInvoice->taxes()->exists()) {
                $recurringInvoice->taxes()->delete();
            }

            $recurringInvoice->delete();
        }

        return true;
    }

    /**
     * Generate every invoice the active schedules have fallen due for, each
     * dated its scheduled day where the company is. The scheduled command runs
     * this; see RecurrenceRunner for the locking and retry rules.
     *
     * @return int the invoices generated
     */
    public function generateDue(): int
    {
        return $this->runner->run(
            RecurringInvoice::query(),
            fn (RecurringInvoice $schedule, string $date) => $this->createInvoiceFromRecurring($schedule, $date),
        );
    }

    /**
     * Generate one invoice from a schedule now, dated today where the company
     * is, if the schedule has started and has not reached its limit. A
     * schedule at its limit is marked completed instead.
     */
    public function generateInvoice(RecurringInvoice $recurringInvoice): void
    {
        if (Carbon::now()->lessThan($recurringInvoice->starts_at)) {
            return;
        }

        $today = Cadence::localDate(Carbon::now(), $recurringInvoice->scheduleTimeZone());

        if (RecurrenceRunner::limitReached($recurringInvoice, $today)) {
            $recurringInvoice->markStatusAsCompleted();

            return;
        }

        DB::transaction(fn () => $this->createInvoiceFromRecurring($recurringInvoice, $today));
    }

    /**
     * The invoice for one occurrence, dated the given day, with its due date
     * counted from that day.
     */
    private function createInvoiceFromRecurring(RecurringInvoice $recurringInvoice, string $date): void
    {
        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($recurringInvoice->company_id)
            ->setCustomer($recurringInvoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setNextNumbers();

        $days = intval(CompanySetting::getSetting('invoice_due_date_days', $recurringInvoice->company_id));

        if (! $days || $days == 'null') {
            $days = 7;
        }

        $newInvoice['creator_id'] = $recurringInvoice->creator_id;
        $newInvoice['invoice_date'] = $date;
        $newInvoice['due_date'] = Carbon::parse($date)->addDays($days)->toDateString();
        $newInvoice['status'] = Invoice::STATUS_DRAFT;
        $newInvoice['company_id'] = $recurringInvoice->company_id;
        $newInvoice['paid_status'] = Invoice::STATUS_UNPAID;
        $newInvoice['sub_total'] = $recurringInvoice->sub_total;
        $newInvoice['tax_per_item'] = $recurringInvoice->tax_per_item;
        $newInvoice['tax_included'] = $recurringInvoice->tax_included;
        $newInvoice['discount_per_item'] = $recurringInvoice->discount_per_item;
        $newInvoice['tax'] = $recurringInvoice->tax;
        $newInvoice['total'] = $recurringInvoice->total;
        $newInvoice['customer_id'] = $recurringInvoice->customer_id;
        $newInvoice['currency_id'] = Customer::find($recurringInvoice->customer_id)->currency_id;
        $newInvoice['template_name'] = $recurringInvoice->template_name;
        $newInvoice['due_amount'] = $recurringInvoice->total;
        $newInvoice['recurring_invoice_id'] = $recurringInvoice->id;
        $newInvoice['discount_val'] = $recurringInvoice->discount_val;
        $newInvoice['discount'] = $recurringInvoice->discount;
        $newInvoice['discount_type'] = $recurringInvoice->discount_type;
        $newInvoice['notes'] = $recurringInvoice->notes;
        $newInvoice['exchange_rate'] = $recurringInvoice->exchange_rate;
        $newInvoice['sales_tax_type'] = $recurringInvoice->sales_tax_type;
        $newInvoice['sales_tax_address_type'] = $recurringInvoice->sales_tax_address_type;
        $newInvoice['base_due_amount'] = MoneyConversion::toBaseMinor($newInvoice['due_amount'], $recurringInvoice->exchange_rate);
        $newInvoice['base_discount_val'] = MoneyConversion::toBaseMinor($recurringInvoice->discount_val, $recurringInvoice->exchange_rate);
        $newInvoice['base_sub_total'] = MoneyConversion::toBaseMinor($recurringInvoice->sub_total, $recurringInvoice->exchange_rate);
        $newInvoice['base_tax'] = MoneyConversion::toBaseMinor($recurringInvoice->tax, $recurringInvoice->exchange_rate);
        $newInvoice['base_total'] = MoneyConversion::toBaseMinor($recurringInvoice->total, $recurringInvoice->exchange_rate);

        // Stamped last: the visible number is rendered from a format that may
        // embed either of the two sequences.
        $newInvoice += [
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
        ];

        $invoice = Invoice::create($newInvoice);
        $invoice->unique_hash = PublicToken::make();
        $invoice->save();

        $recurringInvoice->load('items.taxes');
        $this->documentItemService->createItems($invoice, $recurringInvoice->items->toArray());

        if ($recurringInvoice->taxes()->exists()) {
            $this->documentItemService->createTaxes($invoice, $recurringInvoice->taxes->toArray());
        }

        if ($recurringInvoice->fields()->exists()) {
            $customField = [];

            foreach ($recurringInvoice->fields as $answer) {
                $customField[] = ['id' => $answer->custom_field_id, 'value' => $answer->defaultAnswer];
            }

            $this->customFieldValueWriter->attach($invoice, $customField);
        }

        if ($recurringInvoice->send_automatically == true) {
            $customer = $invoice->customer;

            $data = [
                'body' => CompanySetting::getSetting('invoice_mail_body', $recurringInvoice->company_id),
                'from' => config('mail.from.address'),
                'to' => $recurringInvoice->customer->email,
                'subject' => trans('invoices')['new_invoice'],
                'invoice' => $invoice->toArray(),
                'customer' => $customer->toArray(),
                'company' => Company::find($invoice->company_id),
            ];

            // Sent once the invoice is committed, so a rolled-back run mails nothing.
            DB::afterCommit(fn () => $this->invoiceService->send($invoice, $data));
        }
    }
}
