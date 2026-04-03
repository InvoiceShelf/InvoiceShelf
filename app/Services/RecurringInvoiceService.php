<?php

namespace App\Services;

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

class RecurringInvoiceService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly InvoiceService $invoiceService,
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

    public function generateInvoice(RecurringInvoice $recurringInvoice): void
    {
        if (Carbon::now()->lessThan($recurringInvoice->starts_at)) {
            return;
        }

        if ($recurringInvoice->limit_by == 'DATE') {
            $startDate = Carbon::today()->format('Y-m-d');
            $endDate = $recurringInvoice->limit_date;

            if ($endDate >= $startDate) {
                $this->createInvoiceFromRecurring($recurringInvoice);
                $recurringInvoice->updateNextInvoiceDate();
            } else {
                $recurringInvoice->markStatusAsCompleted();
            }
        } elseif ($recurringInvoice->limit_by == 'COUNT') {
            $invoiceCount = Invoice::where('recurring_invoice_id', $recurringInvoice->id)->count();

            if ($invoiceCount < $recurringInvoice->limit_count) {
                $this->createInvoiceFromRecurring($recurringInvoice);
                $recurringInvoice->updateNextInvoiceDate();
            } else {
                $recurringInvoice->markStatusAsCompleted();
            }
        } else {
            $this->createInvoiceFromRecurring($recurringInvoice);
            $recurringInvoice->updateNextInvoiceDate();
        }
    }

    private function createInvoiceFromRecurring(RecurringInvoice $recurringInvoice): void
    {
        $serial = (new SerialNumberFormatter)
            ->setModel(new Invoice)
            ->setCompany($recurringInvoice->company_id)
            ->setCustomer($recurringInvoice->customer_id)
            ->setNextNumbers();

        $days = intval(CompanySetting::getSetting('invoice_due_date_days', $recurringInvoice->company_id));

        if (! $days || $days == 'null') {
            $days = 7;
        }

        $newInvoice['creator_id'] = $recurringInvoice->creator_id;
        $newInvoice['invoice_date'] = Carbon::today()->format('Y-m-d');
        $newInvoice['due_date'] = Carbon::today()->addDays($days)->format('Y-m-d');
        $newInvoice['status'] = Invoice::STATUS_DRAFT;
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

        if ($recurringInvoice->send_automatically == true) {
            $data = [
                'body' => CompanySetting::getSetting('invoice_mail_body', $recurringInvoice->company_id),
                'from' => config('mail.from.address'),
                'to' => $recurringInvoice->customer->email,
                'subject' => trans('invoices')['new_invoice'],
                'invoice' => $invoice->toArray(),
                'customer' => $invoice->customer->toArray(),
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
