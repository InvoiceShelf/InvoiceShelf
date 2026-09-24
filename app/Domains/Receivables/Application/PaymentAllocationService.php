<?php

namespace App\Domains\Receivables\Application;

use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use App\Support\MoneyConversion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(
        private readonly InvoiceBalanceUpdater $invoiceBalanceUpdater,
    ) {}

    /**
     * Replace the complete allocation set for a payment. Empty allocations are
     * valid and represent unapplied customer credit.
     */
    public function replace(Payment $payment, array $allocations): Payment
    {
        return DB::transaction(function () use ($payment, $allocations): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->replaceLocked($lockedPayment, $allocations);

            return $lockedPayment;
        });
    }

    /**
     * Atomically apply existing unapplied credit to one or more invoices. The
     * supplied rows are additive: existing allocations remain in place.
     */
    public function applyCustomerCredits(int $companyId, int $customerId, array $allocations): void
    {
        DB::transaction(function () use ($companyId, $customerId, $allocations): void {
            $this->assertCreditRows($allocations);

            $paymentIds = collect($allocations)->pluck('payment_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $invoiceIds = collect($allocations)->pluck('invoice_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();

            $payments = Payment::query()
                ->whereIn('id', $paymentIds)
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($payments->count() !== $paymentIds->count()) {
                throw ValidationException::withMessages([
                    'allocations' => ['payment_allocation_payment_not_found'],
                ]);
            }

            $existingInvoiceIds = PaymentAllocation::query()
                ->whereIn('payment_id', $paymentIds)
                ->pluck('invoice_id');
            $allInvoiceIds = $existingInvoiceIds->concat($invoiceIds)->unique()->sort()->values();

            // Lock every invoice before changing any allocation. This is the
            // same lock order used by replaceLocked(), avoiding overspending
            // the balance when two credits target the same invoice.
            Invoice::query()
                ->whereIn('id', $allInvoiceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $existing = $payment->allocations()
                    ->get(['invoice_id', 'amount'])
                    ->map(fn (PaymentAllocation $allocation) => [
                        'invoice_id' => (int) $allocation->invoice_id,
                        'amount' => (int) $allocation->amount,
                    ]);
                $extra = collect($allocations)
                    ->where('payment_id', $payment->id)
                    ->map(fn (array $allocation) => [
                        'invoice_id' => (int) $allocation['invoice_id'],
                        'amount' => (int) $allocation['amount'],
                    ]);

                $target = $existing->concat($extra)
                    ->groupBy('invoice_id')
                    ->map(fn (Collection $rows, $invoiceId) => [
                        'invoice_id' => (int) $invoiceId,
                        'amount' => $rows->sum('amount'),
                    ])
                    ->values()
                    ->all();

                $this->replaceLocked($payment, $target, true);
            }
        });
    }

    private function replaceLocked(Payment $payment, array $allocations, bool $invoicesAlreadyLocked = false): void
    {
        $allocations = $this->normaliseAllocations($allocations);
        $this->assertAllocationTotal($payment, $allocations);

        $oldInvoiceIds = $payment->allocations()->pluck('invoice_id');
        $invoiceIds = collect($allocations)->pluck('invoice_id')->unique()->sort()->values();
        $affectedIds = $oldInvoiceIds->concat($invoiceIds)->unique()->sort()->values();

        // Old and new invoice rows share one sorted lock set. Clearing or
        // moving an allocation changes an old invoice's available balance just
        // as much as allocating to a new invoice does.
        $invoices = $affectedIds->isEmpty()
            ? collect()
            : Invoice::query()
                ->whereIn('id', $affectedIds)
                ->orderBy('id')
                ->when(! $invoicesAlreadyLocked, fn ($query) => $query->lockForUpdate())
                ->get()
                ->keyBy('id');

        if ($invoices->whereIn('id', $invoiceIds)->count() !== $invoiceIds->count()) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_invoice_not_found'],
            ]);
        }

        foreach ($allocations as $allocation) {
            $invoice = $invoices->get($allocation['invoice_id']);
            $this->assertInvoiceCanReceiveAllocation($payment, $invoice, $allocation['amount']);
        }

        $payment->allocations()->delete();

        foreach ($this->baseAmounts($payment, $allocations) as $allocation) {
            $payment->allocations()->create($allocation);
        }

        foreach ($invoices as $invoice) {
            $this->invoiceBalanceUpdater->recalculate($invoice);
        }
    }

    private function normaliseAllocations(array $allocations): array
    {
        $invoiceIds = collect($allocations)->pluck('invoice_id');

        if ($invoiceIds->count() !== $invoiceIds->unique()->count()) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_duplicate_invoice'],
            ]);
        }

        $normalised = collect($allocations)
            ->map(function (array $allocation): array {
                if (! array_key_exists('invoice_id', $allocation) || ! array_key_exists('amount', $allocation)) {
                    throw ValidationException::withMessages([
                        'allocations' => ['payment_allocation_invalid'],
                    ]);
                }

                if (! $this->isInteger($allocation['invoice_id']) || ! $this->isInteger($allocation['amount'])) {
                    throw ValidationException::withMessages([
                        'allocations' => ['payment_allocation_invalid'],
                    ]);
                }

                return [
                    'invoice_id' => (int) $allocation['invoice_id'],
                    'amount' => (int) $allocation['amount'],
                ];
            })
            ->sortBy('invoice_id')
            ->values()
            ->all();

        foreach ($normalised as $allocation) {
            if ($allocation['invoice_id'] < 1 || $allocation['amount'] < 1) {
                throw ValidationException::withMessages([
                    'allocations' => ['payment_allocation_invalid'],
                ]);
            }
        }

        return $normalised;
    }

    private function assertAllocationTotal(Payment $payment, array $allocations): void
    {
        if ((int) $payment->amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['payment_amount_must_be_positive'],
            ]);
        }

        if (collect($allocations)->sum('amount') > (int) $payment->amount) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_exceeds_payment_amount'],
            ]);
        }
    }

    private function assertInvoiceCanReceiveAllocation(Payment $payment, Invoice $invoice, int $amount): void
    {
        if (
            (int) $invoice->company_id !== (int) $payment->company_id
            || (int) $invoice->customer_id !== (int) $payment->customer_id
            || (int) $invoice->currency_id !== (int) $payment->currency_id
        ) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_invoice_mismatch'],
            ]);
        }

        if ($invoice->type !== Invoice::TYPE_INVOICE || $invoice->status === Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_invoice_not_payable'],
            ]);
        }

        $allocatedByOtherPayments = (int) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', '!=', $payment->id)
            ->sum('amount');
        $available = max(0, (int) $invoice->total - $this->invoiceBalanceUpdater->creditedTotal($invoice) - $allocatedByOtherPayments);

        if ($amount > $available) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_exceeds_invoice_balance'],
            ]);
        }
    }

    private function baseAmounts(Payment $payment, array $allocations): array
    {
        $paymentAmount = (int) $payment->amount;
        $paymentBaseAmount = $payment->base_amount === null
            ? MoneyConversion::toBaseMinor($paymentAmount, (float) $payment->exchange_rate ?: 1)
            : (int) $payment->base_amount;
        $allocatedAmount = (int) collect($allocations)->sum('amount');
        $allocatedBaseAmount = 0;

        return collect($allocations)->values()->map(function (array $allocation, int $index) use ($paymentAmount, $paymentBaseAmount, $allocatedAmount, &$allocatedBaseAmount, $allocations): array {
            $isLastFullyAllocatedRow = $allocatedAmount === $paymentAmount && $index === count($allocations) - 1;
            $baseAmount = $isLastFullyAllocatedRow
                ? $paymentBaseAmount - $allocatedBaseAmount
                : $this->proportionalAmount($paymentBaseAmount, $allocation['amount'], $paymentAmount);

            $allocatedBaseAmount += $baseAmount;

            return [
                'invoice_id' => $allocation['invoice_id'],
                'amount' => $allocation['amount'],
                'base_amount' => $baseAmount,
            ];
        })->all();
    }

    private function assertCreditRows(array $allocations): void
    {
        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => ['payment_allocation_required'],
            ]);
        }

        foreach ($allocations as $allocation) {
            if (
                ! isset($allocation['payment_id'], $allocation['invoice_id'], $allocation['amount'])
                || ! $this->isInteger($allocation['payment_id'])
                || ! $this->isInteger($allocation['invoice_id'])
                || ! $this->isInteger($allocation['amount'])
                || (int) $allocation['payment_id'] < 1
                || (int) $allocation['invoice_id'] < 1
                || (int) $allocation['amount'] < 1
            ) {
                throw ValidationException::withMessages([
                    'allocations' => ['payment_allocation_invalid'],
                ]);
            }
        }
    }

    private function isInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Calculate floor(baseAmount * allocationAmount / paymentAmount) without
     * overflowing an intermediate product. Every intermediate stays below the
     * payment amount except the bounded final result.
     */
    private function proportionalAmount(int $baseAmount, int $allocationAmount, int $paymentAmount): int
    {
        $whole = intdiv($baseAmount, $paymentAmount) * $allocationAmount;
        $remainder = $baseAmount % $paymentAmount;
        $quotient = 0;
        $modulo = 0;
        $factor = $remainder;
        $multiplier = $allocationAmount;

        while ($multiplier > 0) {
            if ($multiplier % 2 === 1) {
                if ($modulo >= $paymentAmount - $factor) {
                    $quotient++;
                    $modulo -= $paymentAmount - $factor;
                } else {
                    $modulo += $factor;
                }
            }

            if ($factor >= $paymentAmount - $factor) {
                $factor -= $paymentAmount - $factor;
            } else {
                $factor += $factor;
            }

            $multiplier = intdiv($multiplier, 2);
        }

        return $whole + $quotient;
    }
}
