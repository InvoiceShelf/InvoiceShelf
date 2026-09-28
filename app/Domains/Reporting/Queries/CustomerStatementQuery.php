<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerStatementQuery
{
    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_OUTSTANDING = 'outstanding';

    /**
     * Build a live account statement in the customer's currency.
     */
    public function statement(Customer $customer, string $type, Carbon $from, Carbon $to, int $perPage = 50, int $page = 1): array
    {
        $customer->loadMissing(['company', 'currency']);

        return $type === self::TYPE_OUTSTANDING
            ? $this->outstandingStatement($customer, $to)
            : $this->activityStatement($customer, $from, $to, $perPage, $page);
    }

    /**
     * Add the account-summary fields expected by the customer API without
     * persisting derived balances on customers.
     */
    public function hydrateAccountSummaries(iterable $customers): void
    {
        $customers = collect($customers)->values();

        if ($customers->isEmpty()) {
            return;
        }

        $customerIds = $customers->pluck('id')->all();
        $invoiceTotals = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->select('customer_id')
            ->selectRaw('COALESCE(SUM(due_amount), 0) as invoice_due_amount')
            ->selectRaw('COALESCE(SUM(base_due_amount), 0) as base_invoice_due_amount')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $allocationTotals = PaymentAllocation::query()
            ->select('payment_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as allocated_amount')
            ->selectRaw('COALESCE(SUM(base_amount), 0) as base_allocated_amount')
            ->groupBy('payment_id');

        $paymentTotals = Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->leftJoinSub($allocationTotals, 'allocation_totals', function ($join) {
                $join->on('customer_payments.id', '=', 'allocation_totals.payment_id');
            })
            ->select('customer_payments.customer_id')
            ->selectRaw('COALESCE(SUM(customer_payments.amount), 0) as payment_amount')
            ->selectRaw('COALESCE(SUM(customer_payments.base_amount), 0) as base_payment_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(allocation_totals.allocated_amount, 0)), 0) as allocated_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(allocation_totals.base_allocated_amount, 0)), 0) as base_allocated_amount')
            ->groupBy('customer_payments.customer_id')
            ->get()
            ->keyBy('customer_id');

        foreach ($customers as $customer) {
            $invoice = $invoiceTotals->get($customer->id);
            $payment = $paymentTotals->get($customer->id);

            $invoiceDue = (int) ($invoice->invoice_due_amount ?? 0);
            $baseInvoiceDue = (int) ($invoice->base_invoice_due_amount ?? 0);
            $paymentTotal = (int) ($payment->payment_amount ?? 0);
            $basePaymentTotal = (int) ($payment->base_payment_amount ?? 0);
            $allocated = (int) ($payment->allocated_amount ?? 0);
            $baseAllocated = (int) ($payment->base_allocated_amount ?? 0);

            $credit = max(0, $paymentTotal - $allocated);
            $baseCredit = max(0, $basePaymentTotal - $baseAllocated);

            $customer->setAttribute('invoice_due_amount', $invoiceDue);
            $customer->setAttribute('base_invoice_due_amount', $baseInvoiceDue);
            $customer->setAttribute('available_credit', $credit);
            $customer->setAttribute('base_available_credit', $baseCredit);
            $customer->setAttribute('account_balance', $invoiceDue - $credit);
            $customer->setAttribute('base_account_balance', $baseInvoiceDue - $baseCredit);

            // Keep this long-standing response field meaningful for clients
            // which have not yet adopted the richer account summary.
            $customer->setAttribute('due_amount', $invoiceDue);
            $customer->setAttribute('base_due_amount', $baseInvoiceDue);
        }
    }

    public function accountSummary(Customer $customer): array
    {
        $this->hydrateAccountSummaries([$customer]);

        return [
            'invoice_due_amount' => (int) $customer->invoice_due_amount,
            'base_invoice_due_amount' => (int) $customer->base_invoice_due_amount,
            'available_credit' => (int) $customer->available_credit,
            'base_available_credit' => (int) $customer->base_available_credit,
            'account_balance' => (int) $customer->account_balance,
            'base_account_balance' => (int) $customer->base_account_balance,
        ];
    }

    private function activityStatement(Customer $customer, Carbon $from, Carbon $to, int $perPage, int $page): array
    {
        $openingBalance = $this->activityBalanceBefore($customer, $from);
        $entries = collect();

        $documents = $this->statementDocuments($customer)
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'invoice_date', 'invoice_number', 'type', 'total', 'base_total']);

        foreach ($documents as $document) {
            $isCreditNote = $document->type === Invoice::TYPE_CREDIT_NOTE;
            $entries->push([
                'id' => $document->id,
                'date' => Carbon::parse($document->invoice_date)->toDateString(),
                'entry_type' => $isCreditNote ? 'credit_note' : 'invoice',
                'reference' => $document->invoice_number,
                'description' => $isCreditNote ? __('Credit note') : __('Invoice'),
                'debit_amount' => $isCreditNote ? 0 : (int) $document->total,
                'credit_amount' => $isCreditNote ? abs((int) $document->total) : 0,
                'base_debit_amount' => $isCreditNote ? 0 : (int) $document->base_total,
                'base_credit_amount' => $isCreditNote ? abs((int) $document->base_total) : 0,
                'sort_order' => $isCreditNote ? 1 : 0,
            ]);
        }

        $payments = Payment::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'payment_date', 'payment_number', 'amount', 'base_amount']);

        foreach ($payments as $payment) {
            $entries->push([
                'id' => $payment->id,
                'date' => Carbon::parse($payment->payment_date)->toDateString(),
                'entry_type' => 'payment',
                'reference' => $payment->payment_number,
                'description' => __('Payment'),
                'debit_amount' => 0,
                'credit_amount' => (int) $payment->amount,
                'base_debit_amount' => 0,
                'base_credit_amount' => (int) $payment->base_amount,
                'sort_order' => 2,
            ]);
        }

        $entries = $entries
            ->sort(fn (array $left, array $right) => [$left['date'], $left['sort_order'], $left['id']] <=> [$right['date'], $right['sort_order'], $right['id']])
            ->values();

        $runningBalance = $openingBalance['amount'];
        $baseRunningBalance = $openingBalance['base_amount'];
        $entries = $entries->map(function (array $entry) use (&$runningBalance, &$baseRunningBalance) {
            $runningBalance += $entry['debit_amount'] - $entry['credit_amount'];
            $baseRunningBalance += $entry['base_debit_amount'] - $entry['base_credit_amount'];
            $entry['balance'] = $runningBalance;
            $entry['base_balance'] = $baseRunningBalance;
            unset($entry['sort_order']);

            return $entry;
        });

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'type' => self::TYPE_ACTIVITY,
            'customer' => $customer,
            'currency' => $customer->currency,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'opening_balance' => $openingBalance['amount'],
            'base_opening_balance' => $openingBalance['base_amount'],
            'closing_balance' => $runningBalance,
            'base_closing_balance' => $baseRunningBalance,
            'entries' => $paginator,
        ];
    }

    private function outstandingStatement(Customer $customer, Carbon $asOf): array
    {
        $invoices = Invoice::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->where('invoice_date', '<=', $asOf->toDateString())
            ->withSum([
                'creditNotes as credited_amount' => fn ($query) => $query->where('invoice_date', '<=', $asOf->toDateString()),
            ], 'total')
            ->withSum([
                'creditNotes as base_credited_amount' => fn ($query) => $query->where('invoice_date', '<=', $asOf->toDateString()),
            ], 'base_total')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'invoice_date', 'due_date', 'invoice_number', 'total', 'base_total']);

        $invoiceAllocations = $this->allocationTotalsForInvoices($invoices->pluck('id'), $asOf);
        $openInvoices = $invoices->map(function (Invoice $invoice) use ($invoiceAllocations) {
            $allocation = $invoiceAllocations->get($invoice->id, ['amount' => 0, 'base_amount' => 0]);
            $credit = max(0, -(int) ($invoice->credited_amount ?? 0));
            $baseCredit = max(0, -(int) ($invoice->base_credited_amount ?? 0));
            $remaining = max(0, (int) $invoice->total - $credit - $allocation['amount']);
            $baseRemaining = max(0, (int) $invoice->base_total - $baseCredit - $allocation['base_amount']);

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => Carbon::parse($invoice->invoice_date)->toDateString(),
                'due_date' => $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : null,
                'original_amount' => (int) $invoice->total,
                'allocated_amount' => $allocation['amount'],
                'credit_amount' => $credit,
                'applied_amount' => $allocation['amount'] + $credit,
                'remaining_amount' => $remaining,
                'base_original_amount' => (int) $invoice->base_total,
                'base_allocated_amount' => $allocation['base_amount'],
                'base_credit_amount' => $baseCredit,
                'base_applied_amount' => $allocation['base_amount'] + $baseCredit,
                'base_remaining_amount' => $baseRemaining,
            ];
        })->filter(fn (array $invoice) => $invoice['remaining_amount'] > 0)->values();

        $payments = Payment::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('payment_date', '<=', $asOf->toDateString())
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'payment_date', 'payment_number', 'amount', 'base_amount']);
        $paymentAllocations = $this->allocationTotalsForPayments($payments->pluck('id'), $asOf);

        $credits = $payments->map(function (Payment $payment) use ($paymentAllocations) {
            $allocation = $paymentAllocations->get($payment->id, ['amount' => 0, 'base_amount' => 0]);
            $available = max(0, (int) $payment->amount - $allocation['amount']);

            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_date' => Carbon::parse($payment->payment_date)->toDateString(),
                'amount' => (int) $payment->amount,
                'allocated_amount' => $allocation['amount'],
                'available_amount' => $available,
                'base_amount' => (int) $payment->base_amount,
                'base_allocated_amount' => $allocation['base_amount'],
                'base_available_amount' => max(0, (int) $payment->base_amount - $allocation['base_amount']),
            ];
        })->filter(fn (array $payment) => $payment['available_amount'] > 0)->values();

        $invoiceDue = (int) $openInvoices->sum('remaining_amount');
        $baseInvoiceDue = (int) $openInvoices->sum('base_remaining_amount');
        $availableCredit = (int) $credits->sum('available_amount');
        $baseAvailableCredit = (int) $credits->sum('base_available_amount');

        return [
            'type' => self::TYPE_OUTSTANDING,
            'customer' => $customer,
            'currency' => $customer->currency,
            'as_of' => $asOf->toDateString(),
            'invoices' => $openInvoices,
            'credits' => $credits,
            'invoice_due_amount' => $invoiceDue,
            'base_invoice_due_amount' => $baseInvoiceDue,
            'available_credit' => $availableCredit,
            'base_available_credit' => $baseAvailableCredit,
            'account_balance' => $invoiceDue - $availableCredit,
            'base_account_balance' => $baseInvoiceDue - $baseAvailableCredit,
        ];
    }

    private function activityBalanceBefore(Customer $customer, Carbon $from): array
    {
        $documents = $this->statementDocuments($customer)
            ->where('invoice_date', '<', $from->toDateString());
        $payments = Payment::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('payment_date', '<', $from->toDateString());

        return [
            'amount' => (int) $documents->sum('total') - (int) $payments->sum('amount'),
            'base_amount' => (int) $documents->sum('base_total') - (int) $payments->sum('base_amount'),
        ];
    }

    private function statementDocuments(Customer $customer)
    {
        return Invoice::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where(function ($query) {
                $query->where('type', Invoice::TYPE_CREDIT_NOTE)
                    ->orWhere(function ($query) {
                        $query->where('type', Invoice::TYPE_INVOICE)
                            ->where('status', '!=', Invoice::STATUS_DRAFT);
                    });
            });
    }

    private function allocationTotalsForInvoices(Collection $invoiceIds, Carbon $asOf): Collection
    {
        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        return PaymentAllocation::query()
            ->join('customer_payments', 'customer_payments.id', '=', 'customer_payment_allocations.payment_id')
            ->whereIn('customer_payment_allocations.invoice_id', $invoiceIds)
            ->where('customer_payments.payment_date', '<=', $asOf->toDateString())
            ->where('customer_payment_allocations.created_at', '<=', $asOf->copy()->endOfDay())
            ->selectRaw('customer_payment_allocations.invoice_id, SUM(customer_payment_allocations.amount) as amount, SUM(customer_payment_allocations.base_amount) as base_amount')
            ->groupBy('customer_payment_allocations.invoice_id')
            ->get()
            ->mapWithKeys(fn (PaymentAllocation $allocation) => [$allocation->invoice_id => [
                'amount' => (int) $allocation->amount,
                'base_amount' => (int) $allocation->base_amount,
            ]]);
    }

    private function allocationTotalsForPayments(Collection $paymentIds, ?Carbon $asOf = null): Collection
    {
        if ($paymentIds->isEmpty()) {
            return collect();
        }

        $query = PaymentAllocation::query()
            ->whereIn('payment_id', $paymentIds)
            ->selectRaw('payment_id, SUM(amount) as amount, SUM(base_amount) as base_amount')
            ->groupBy('payment_id');

        if ($asOf) {
            $query->where('created_at', '<=', $asOf->copy()->endOfDay());
        }

        return $query
            ->get()
            ->mapWithKeys(fn (PaymentAllocation $allocation) => [$allocation->payment_id => [
                'amount' => (int) $allocation->amount,
                'base_amount' => (int) $allocation->base_amount,
            ]]);
    }
}
