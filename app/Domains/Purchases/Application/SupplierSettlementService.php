<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Contracts\DocumentNumberAssigner;
use App\Domains\Purchases\Http\Requests\SupplierAllocationRequest;
use App\Domains\Purchases\Http\Requests\SupplierPaymentRequest;
use App\Domains\Purchases\Http\Requests\SupplierRefundRequest;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Support\ProportionalAmount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierSettlementService
{
    public function __construct(private readonly DocumentNumberAssigner $numbers) {}

    public function recordPayment(int $companyId, ?int $actorId, array $data): SupplierPayment
    {
        $data = Validator::make($data, SupplierPaymentRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($companyId, $actorId, $data): SupplierPayment {
            $supplier = PurchaseInputs::lockSupplier($companyId, $data['supplier_id']);
            PurchaseInputs::ensure($supplier->enabled, 'supplier_id', 'This supplier is inactive.');
            $money = PurchaseInputs::money($companyId, $data);
            $payment = SupplierPayment::query()->create([
                ...Arr::only($data, ['supplier_id', 'amount', 'payment_date', 'payment_method_id', 'reference', 'notes']),
                ...$money, 'company_id' => $companyId, 'creator_id' => $actorId,
                'base_amount' => PurchaseInputs::base($data['amount'], $money['exchange_rate']), 'status' => 'OPEN',
                ...$this->numbers->next(SupplierPayment::class, $companyId),
            ]);
            $this->replaceAllocations($payment, $data['allocations'] ?? [], $actorId);

            return $payment->fresh(['supplier', 'currency', 'allocations.bill', 'refunds']);
        });
    }

    /** All writes for one supplier take this lock first, including document edits and refunds. */
    public function replaceAllocations(SupplierPayment|SupplierCredit $source, array $rows, ?int $actorId): SupplierPayment|SupplierCredit
    {
        Validator::make(['allocations' => $rows], SupplierAllocationRequest::rulesFor($source->company_id))->validate();

        return DB::transaction(function () use ($source, $rows) {
            PurchaseInputs::lockSupplier($source->company_id, $source->supplier_id);
            $source = $this->lockSource($source);
            PurchaseInputs::ensure($source->status === 'OPEN', 'allocations', 'Only posted records can be allocated.');
            $total = $source instanceof SupplierPayment ? $source->amount : $source->total;
            $refunded = (int) $source->refunds->where('status', 'OPEN')->sum('amount');
            PurchaseInputs::ensure(array_sum(array_column($rows, 'amount')) <= $total - $refunded, 'allocations', 'Allocations exceed the available balance.');
            $old = $source->allocations->map->only(['bill_id', 'amount'])->all();
            $ids = array_unique([...array_column($old, 'bill_id'), ...array_column($rows, 'bill_id')]);
            sort($ids);
            $bills = Bill::query()->forCompany($source->company_id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                $bill = $bills->get($row['bill_id']);
                PurchaseInputs::ensure($bill !== null && $bill->supplier_id == $source->supplier_id && $bill->currency_id == $source->currency_id && $bill->status === 'OPEN', 'allocations', 'Every bill must be open and belong to this supplier, company, and currency.');
                $this->recalculate($bill);
                $own = (int) $source->allocations->where('bill_id', $bill->id)->sum('amount');
                PurchaseInputs::ensure($row['amount'] <= $bill->due_amount + $own, 'allocations', 'An allocation exceeds the bill balance.');
            }
            $source->allocations()->delete();
            $used = 0;
            $baseTotal = $source instanceof SupplierPayment ? $source->base_amount : $source->base_total;
            foreach (collect($rows)->sortBy('bill_id') as $row) {
                $amount = (int) $row['amount'];
                $source->allocations()->create(['company_id' => $source->company_id, 'bill_id' => $row['bill_id'], 'amount' => $amount, 'base_amount' => ProportionalAmount::slice($baseTotal, $used, $used + $amount, $total)]);
                $used += $amount;
                $bill = $bills->get($row['bill_id']);
                $bill->update(['financial_locked_at' => $bill->financial_locked_at ?? now()]);
            }
            foreach ($bills as $bill) {
                $this->recalculate($bill);
            }

            return $source->fresh(['allocations.bill', 'refunds', 'currency', 'supplier']);
        });
    }

    public function recordRefund(int $companyId, ?int $actorId, array $data): SupplierRefund
    {
        $data = Validator::make($data, SupplierRefundRequest::rulesFor($companyId))->validate();

        // Resolve the immutable supplier identity before opening the transaction.
        // A non-locking read inside it would establish an old MySQL REPEATABLE READ snapshot.
        $source = isset($data['supplier_payment_id'])
            ? SupplierPayment::query()->forCompany($companyId)->findOrFail($data['supplier_payment_id'])
            : SupplierCredit::query()->forCompany($companyId)->findOrFail($data['supplier_credit_id']);

        return DB::transaction(function () use ($companyId, $actorId, $data, $source): SupplierRefund {
            PurchaseInputs::lockSupplier($companyId, $source->supplier_id);
            $source = $this->lockSource($source);
            PurchaseInputs::ensure($data['amount'] <= $source->available_amount, 'amount', 'The refund exceeds the available balance. Release allocations before refunding an applied payment.');
            $money = PurchaseInputs::money($companyId, [...$data, 'currency_id' => $source->currency_id]);
            $refund = SupplierRefund::query()->create([
                ...Arr::only($data, ['supplier_payment_id', 'supplier_credit_id', 'amount', 'payment_date', 'payment_method_id', 'reference', 'notes']),
                ...$money, 'company_id' => $companyId, 'creator_id' => $actorId, 'supplier_id' => $source->supplier_id,
                'base_amount' => PurchaseInputs::base($data['amount'], $money['exchange_rate']), 'status' => 'OPEN',
                ...$this->numbers->next(SupplierRefund::class, $companyId),
            ]);

            return $refund->load(['supplier', 'currency']);
        });
    }

    public function void(SupplierPayment|SupplierRefund $record, string $reason, ?int $actorId): void
    {
        DB::transaction(function () use ($record, $reason, $actorId): void {
            PurchaseInputs::lockSupplier($record->company_id, $record->supplier_id);
            $record = $record::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->status === 'VOID') {
                return;
            }
            PurchaseInputs::ensure(trim($reason) !== '', 'reason', 'A reason is required.');
            if ($record instanceof SupplierPayment) {
                PurchaseInputs::ensure(! $record->refunds()->where('status', 'OPEN')->exists(), 'payment', 'Void the linked refunds first.');
                $this->replaceAllocations($record, [], $actorId);
            }
            $record->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);
        });
    }

    private function lockSource(SupplierPayment|SupplierCredit $source): SupplierPayment|SupplierCredit
    {
        $source = $source::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
        $source->setRelation('allocations', $source->allocations()->orderBy('id')->lockForUpdate()->get());
        $source->setRelation('refunds', $source->refunds()->orderBy('id')->lockForUpdate()->get());

        return $source;
    }

    public function recalculate(Bill $bill): void
    {
        $settled = (int) $bill->paymentAllocations()->lockForUpdate()->get()->sum('amount') + (int) $bill->creditAllocations()->lockForUpdate()->get()->sum('amount');
        $due = $bill->total - $settled;
        PurchaseInputs::ensure($due >= 0, 'bill', 'Settlements exceed the bill total.');
        $bill->update(['due_amount' => $due, 'base_due_amount' => ProportionalAmount::floor($bill->base_total, $due, $bill->total)]);
    }
}
