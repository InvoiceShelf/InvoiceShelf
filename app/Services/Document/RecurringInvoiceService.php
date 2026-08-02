<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Http\Requests\RecurringInvoiceRequest;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringInvoiceService
{
    private const MAX_INVOICES_PER_RUN = 100;

    private const MAX_INVOICES_PER_TEMPLATE = 10;

    private const MAX_TEMPLATES_PER_RUN = 100;

    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly InvoiceService $invoiceService,
        private readonly RecurringInvoiceScheduleService $schedule,
    ) {}

    public function create(RecurringInvoiceRequest $request): RecurringInvoice
    {
        $recurringInvoice = RecurringInvoice::create($request->getRecurringInvoicePayload());

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $recurringInvoice['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($recurringInvoice);
        }

        $this->createItems($recurringInvoice, $request->items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->createTaxes($recurringInvoice, $request->taxes);
        }

        if ($request->customFields) {
            $recurringInvoice->addCustomFields($request->customFields);
        }

        return $recurringInvoice;
    }

    public function update(RecurringInvoice $recurringInvoice, RecurringInvoiceRequest $request): RecurringInvoice
    {
        $data = $request->getRecurringInvoicePayload();

        $recurringInvoice->update($data);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($recurringInvoice);
        }

        $recurringInvoice->items()->delete();
        $this->createItems($recurringInvoice, $request->items);

        $recurringInvoice->taxes()->delete();
        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->createTaxes($recurringInvoice, $request->taxes);
        }

        if ($request->customFields) {
            $recurringInvoice->updateCustomFields($request->customFields);
        }

        return $recurringInvoice;
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $recurringInvoice = RecurringInvoice::find($id);

            if ($recurringInvoice->invoices()->exists()) {
                $recurringInvoice->invoices()->update(['recurring_invoice_id' => null]);
            }

            if ($recurringInvoice->items()->exists()) {
                $recurringInvoice->items()->delete();
            }

            if ($recurringInvoice->taxes()->exists()) {
                $recurringInvoice->taxes()->delete();
            }

            $recurringInvoice->delete();
        }

        return true;
    }

    public function generateDueInvoices(): int
    {
        $now = Carbon::now($this->schedule->applicationTimezone());
        $nowString = $now->format('Y-m-d H:i:s');
        $dueTemplateIds = RecurringInvoice::query()
            ->where('status', RecurringInvoice::ACTIVE)
            ->whereNotNull('next_invoice_at')
            ->where('next_invoice_at', '<=', $nowString)
            ->orderBy('next_invoice_at')
            ->orderBy('id')
            ->limit(self::MAX_TEMPLATES_PER_RUN)
            ->pluck('id');

        $generated = 0;
        $finishedTemplateIds = [];

        // Visit every due template once before starting another pass. This lets a
        // busy, long-overdue template catch up without starving the rest.
        for ($round = 0; $round < self::MAX_INVOICES_PER_TEMPLATE && $generated < self::MAX_INVOICES_PER_RUN; $round++) {
            foreach ($dueTemplateIds as $id) {
                if ($generated >= self::MAX_INVOICES_PER_RUN) {
                    break 2;
                }

                if (isset($finishedTemplateIds[$id])) {
                    continue;
                }

                try {
                    $invoice = $this->generateDueInvoice((int) $id, $now);

                    if ($invoice) {
                        $generated++;
                        $this->sendAutomatically($invoice);
                    } else {
                        $finishedTemplateIds[$id] = true;
                    }
                } catch (Throwable $exception) {
                    // A broken template remains due for the next invocation, but
                    // retrying it in every catch-up round would only duplicate
                    // work and log noise while starving healthy templates.
                    $finishedTemplateIds[$id] = true;

                    Log::error('Unable to generate recurring invoice.', [
                        'recurring_invoice_id' => $id,
                        'exception' => $exception,
                    ]);
                }
            }
        }

        return $generated;
    }

    private function generateDueInvoice(int $recurringInvoiceId, Carbon $now): ?Invoice
    {
        return DB::transaction(function () use ($recurringInvoiceId, $now) {
            $recurringInvoice = RecurringInvoice::query()
                ->lockForUpdate()
                ->find($recurringInvoiceId);

            if (! $recurringInvoice
                || $recurringInvoice->status !== RecurringInvoice::ACTIVE
                || ! $recurringInvoice->next_invoice_at
                || $recurringInvoice->next_invoice_at > $now->format('Y-m-d H:i:s')) {
                return null;
            }

            $timezone = $this->schedule->companyTimezone($recurringInvoice->company_id);
            $occurrence = $this->schedule->fromStored($recurringInvoice->next_invoice_at, $recurringInvoice->company_id);
            $invoiceCount = null;

            if ($recurringInvoice->limit_by === RecurringInvoice::DATE
                && (! $recurringInvoice->limit_date || $occurrence->toDateString() > $recurringInvoice->limit_date)) {
                $recurringInvoice->update(['status' => RecurringInvoice::COMPLETED]);

                return null;
            }

            if ($recurringInvoice->limit_by === RecurringInvoice::COUNT) {
                $invoiceCount = $recurringInvoice->invoices()->count();

                if (! $recurringInvoice->limit_count || $invoiceCount >= $recurringInvoice->limit_count) {
                    $recurringInvoice->update(['status' => RecurringInvoice::COMPLETED]);

                    return null;
                }
            }

            $invoice = $this->createInvoiceFromRecurring($recurringInvoice, $occurrence);
            $nextOccurrence = $this->schedule->nextOccurrence($recurringInvoice->frequency, $occurrence, $timezone);

            $complete = ($recurringInvoice->limit_by === RecurringInvoice::COUNT
                    && $invoiceCount + 1 >= $recurringInvoice->limit_count)
                || ($recurringInvoice->limit_by === RecurringInvoice::DATE
                    && $nextOccurrence->toDateString() > $recurringInvoice->limit_date);

            $recurringInvoice->update([
                'next_invoice_at' => $this->schedule->toStored($nextOccurrence),
                'status' => $complete ? RecurringInvoice::COMPLETED : RecurringInvoice::ACTIVE,
            ]);

            return $invoice;
        });
    }

    private function createInvoiceFromRecurring(RecurringInvoice $recurringInvoice, Carbon $occurrence): Invoice
    {
        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($recurringInvoice->company_id)
            ->setCustomer($recurringInvoice->customer_id)
            ->setSequenceScope(['type' => Invoice::TYPE_INVOICE])
            ->setOccurrenceDate($occurrence)
            ->setNextNumbers();

        $days = intval(CompanySetting::getSetting('invoice_due_date_days', $recurringInvoice->company_id));

        if (! $days || $days == 'null') {
            $days = 7;
        }

        $newInvoice['creator_id'] = $recurringInvoice->creator_id;
        $newInvoice['invoice_date'] = $occurrence->toDateString();
        $newInvoice['due_date'] = $occurrence->copy()->addDays($days)->toDateString();
        $newInvoice['status'] = Invoice::STATUS_DRAFT;
        $newInvoice['type'] = Invoice::TYPE_INVOICE;
        $newInvoice['company_id'] = $recurringInvoice->company_id;
        $newInvoice['paid_status'] = Invoice::STATUS_UNPAID;
        $newInvoice['sub_total'] = $recurringInvoice->sub_total;
        $newInvoice['tax_per_item'] = $recurringInvoice->tax_per_item;
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
        $newInvoice['invoice_number'] = $serial->getNextNumber();
        $newInvoice['sequence_number'] = $serial->nextSequenceNumber;
        $newInvoice['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
        $newInvoice['base_due_amount'] = $recurringInvoice->exchange_rate * $recurringInvoice->due_amount;
        $newInvoice['base_discount_val'] = $recurringInvoice->exchange_rate * $recurringInvoice->discount_val;
        $newInvoice['base_sub_total'] = $recurringInvoice->exchange_rate * $recurringInvoice->sub_total;
        $newInvoice['base_tax'] = $recurringInvoice->exchange_rate * $recurringInvoice->tax;
        $newInvoice['base_total'] = $recurringInvoice->exchange_rate * $recurringInvoice->total;
        $invoice = Invoice::create($newInvoice);
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        $recurringInvoice->load('items.taxes');
        $this->documentItemService->createItems($invoice, $recurringInvoice->items->toArray());

        if ($recurringInvoice->taxes()->exists()) {
            $this->documentItemService->createTaxes($invoice, $recurringInvoice->taxes->toArray());
        }

        if ($recurringInvoice->fields()->exists()) {
            $customField = [];

            foreach ($recurringInvoice->fields as $data) {
                $customField[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $invoice->addCustomFields($customField);
        }

        return $invoice;
    }

    private function sendAutomatically(Invoice $invoice): void
    {
        $recurringInvoice = $invoice->recurringInvoice;

        if (! $recurringInvoice?->send_automatically) {
            return;
        }

        try {
            $this->invoiceService->send($invoice, [
                'body' => CompanySetting::getSetting('invoice_mail_body', $invoice->company_id),
                'from' => config('mail.from.address'),
                'to' => $invoice->customer->email,
                'subject' => trans('invoices')['new_invoice'],
                'invoice' => $invoice->toArray(),
                'customer' => $invoice->customer->toArray(),
                'company' => Company::find($invoice->company_id),
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to send automatically generated recurring invoice.', [
                'invoice_id' => $invoice->id,
                'recurring_invoice_id' => $recurringInvoice->id,
                'exception' => $exception,
            ]);
        }
    }

    private function createItems(RecurringInvoice $recurringInvoice, array $items): void
    {
        foreach ($items as $item) {
            $item['company_id'] = $recurringInvoice->company_id;
            $createdItem = $recurringInvoice->items()->create($item);
            if (array_key_exists('taxes', $item) && $item['taxes']) {
                foreach ($item['taxes'] as $tax) {
                    $tax['company_id'] = $recurringInvoice->company_id;
                    if (gettype($tax['amount']) !== 'NULL') {
                        $createdItem->taxes()->create($tax);
                    }
                }
            }
        }
    }

    private function createTaxes(RecurringInvoice $recurringInvoice, array $taxes): void
    {
        foreach ($taxes as $tax) {
            $tax['company_id'] = $recurringInvoice->company_id;

            if (gettype($tax['amount']) !== 'NULL') {
                $recurringInvoice->taxes()->create($tax);
            }
        }
    }
}
