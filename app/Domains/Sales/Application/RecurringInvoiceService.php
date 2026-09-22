<?php

namespace App\Domains\Sales\Application;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Sales\Contracts\DocumentExchangeRateRecorder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Facades\Hashids;
use App\Support\Hashids\HashidConnection;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringInvoiceService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly InvoiceService $invoiceService,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly DocumentExchangeRateRecorder $exchangeRateRecorder,
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

        $this->createItems($recurringInvoice, $items);

        if ($taxes) {
            $this->createTaxes($recurringInvoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($recurringInvoice, $customFields);
        }

        return $recurringInvoice;
    }

    public function update(
        RecurringInvoice $recurringInvoice,
        array $attributes,
        array $items,
        ?array $taxes = null,
        ?iterable $customFields = null,
    ): RecurringInvoice {
        $recurringInvoice->update($attributes);

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
        $this->createItems($recurringInvoice, $items);

        $recurringInvoice->taxes()->delete();
        if ($taxes) {
            $this->createTaxes($recurringInvoice, $taxes);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($recurringInvoice, $customFields);
        }

        return $recurringInvoice;
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
     * Mint one invoice from a schedule, if the schedule is still owed one.
     *
     * `$advanceSchedule` is false when the caller has already claimed the row
     * by moving `next_invoice_at` on itself, which is how the scheduled
     * command stops two runs in the same minute from billing twice.
     */
    public function generateInvoice(RecurringInvoice $recurringInvoice, bool $advanceSchedule = true): void
    {
        if (Carbon::now()->lessThan($recurringInvoice->starts_at)) {
            return;
        }

        if ($recurringInvoice->limit_by == 'DATE') {
            $startDate = Carbon::today()->format('Y-m-d');
            $endDate = $recurringInvoice->limit_date;

            if ($endDate >= $startDate) {
                $this->createInvoiceFromRecurring($recurringInvoice);
                $this->advance($recurringInvoice, $advanceSchedule);
            } else {
                $recurringInvoice->markStatusAsCompleted();
            }
        } elseif ($recurringInvoice->limit_by == 'COUNT') {
            $invoiceCount = Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count();

            if ($invoiceCount < $recurringInvoice->limit_count) {
                $this->createInvoiceFromRecurring($recurringInvoice);
                $this->advance($recurringInvoice, $advanceSchedule);
            } else {
                $recurringInvoice->markStatusAsCompleted();
            }
        } else {
            $this->createInvoiceFromRecurring($recurringInvoice);
            $this->advance($recurringInvoice, $advanceSchedule);
        }
    }

    /**
     * Move the schedule on, unless the caller already did it.
     */
    private function advance(RecurringInvoice $recurringInvoice, bool $advanceSchedule): void
    {
        if ($advanceSchedule) {
            $recurringInvoice->updateNextInvoiceDate();
        }
    }

    private function createInvoiceFromRecurring(RecurringInvoice $recurringInvoice): void
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
        $newInvoice['invoice_date'] = Carbon::today()->toDateString();
        $newInvoice['due_date'] = Carbon::today()->addDays($days)->toDateString();
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
        $newInvoice['base_due_amount'] = $recurringInvoice->exchange_rate * $recurringInvoice->due_amount;
        $newInvoice['base_discount_val'] = $recurringInvoice->exchange_rate * $recurringInvoice->discount_val;
        $newInvoice['base_sub_total'] = $recurringInvoice->exchange_rate * $recurringInvoice->sub_total;
        $newInvoice['base_tax'] = $recurringInvoice->exchange_rate * $recurringInvoice->tax;
        $newInvoice['base_total'] = $recurringInvoice->exchange_rate * $recurringInvoice->total;

        // Stamped last: the visible number is rendered from a format that may
        // embed either of the two sequences.
        $newInvoice += [
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
        ];

        $invoice = Invoice::create($newInvoice);
        $invoice->unique_hash = Hashids::connection(HashidConnection::Invoice->value)->encode($invoice->id);
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

            $this->invoiceService->send($invoice, $data);
        }
    }

    private function createItems(RecurringInvoice $recurringInvoice, array $items): void
    {
        foreach ($items as $item) {
            $item['company_id'] = $recurringInvoice->company_id;
            $createdItem = $recurringInvoice->items()->create($item);
            if (array_key_exists('taxes', $item) && $item['taxes']) {
                foreach ($item['taxes'] as $tax) {
                    if (empty($tax['tax_type_id'])) {
                        continue;
                    }

                    $tax['company_id'] = $recurringInvoice->company_id;
                    if (gettype($tax['amount']) !== 'NULL') {
                        $createdItem->taxes()->create($tax);
                    }
                }
            }
        }
    }

    /**
     * Write the template's own tax rows, skipping the ones carrying no amount.
     */
    private function createTaxes(RecurringInvoice $recurringInvoice, array $taxes): void
    {
        foreach ($taxes as $tax) {
            if (gettype($tax['amount']) !== 'NULL') {
                $tax['company_id'] = $recurringInvoice->company_id;
                $recurringInvoice->taxes()->create($tax);
            }
        }
    }
}
