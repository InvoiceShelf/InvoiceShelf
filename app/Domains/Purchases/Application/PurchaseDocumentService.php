<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Http\Requests\BillRequest;
use App\Domains\Purchases\Http\Requests\SupplierCreditRequest;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierCreditItem;
use App\Domains\Taxation\Models\TaxType;
use App\Support\CreditNoteAmounts;
use App\Support\DocumentTaxes;
use App\Support\ProportionalAmount;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseDocumentService
{
    public function __construct(private readonly SupplierSettlementService $settlements, private readonly PurchaseCustomFields $customFields) {}

    public function saveBill(?Bill $bill, int $companyId, ?int $actorId, array $data): Bill
    {
        $data = Validator::make($data, BillRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($bill, $companyId, $actorId, $data): Bill {
            // Changing an unsettled bill's supplier locks both contacts in a stable order.
            $suppliers = Supplier::query()->forCompany($companyId)
                ->whereIn('id', array_filter([$data['supplier_id'], $bill?->supplier_id]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $supplier = $suppliers->get($data['supplier_id']);
            PurchaseInputs::ensure($supplier !== null, 'supplier_id', 'Supplier not found.');
            PurchaseInputs::ensure($supplier->enabled, 'supplier_id', 'This supplier is inactive.');
            $money = PurchaseInputs::money($companyId, $data);
            $record = $bill ? Bill::query()->forCompany($companyId)->lockForUpdate()->findOrFail($bill->id) : new Bill(['company_id' => $companyId, 'creator_id' => $actorId]);
            $answers = $this->customFields->resolve($companyId, 'Bill', $data['customFields'] ?? [], $this->customFields->saved($record));
            if ($bill) {
                PurchaseInputs::ensure($record->status !== 'VOID', 'bill', 'A void bill cannot be edited.');
                if ($record->financial_locked_at) {
                    PurchaseInputs::ensure($this->unchanged($record, $data, $money), 'items', 'Financial details are locked after settlement or credit. Use a supplier credit to adjust this bill.');
                    $record->update(Arr::only($data, ['reference', 'due_date', 'notes']));
                    $this->customFields->save($record, $answers);
                    PurchaseAudit::record($record, 'details_updated', $actorId);

                    return $record->load(['supplier', 'currency', 'items']);
                }
            }
            $items = $this->composeItems($companyId, $data['items'], $money['exchange_rate'], (bool) ($data['tax_included'] ?? false));
            $record->fill([
                ...Arr::only($data, ['supplier_id', 'reference', 'document_date', 'due_date', 'notes', 'tax_included']),
                ...$money, ...$this->totals($items),
                'status' => $bill && $record->status === 'OPEN' ? 'OPEN' : ($data['status'] ?? 'DRAFT'),
                'supplier_snapshot' => $supplier->only(['name', 'contact_name', 'email', 'tax_id', 'addresses']),
            ])->save();
            $this->customFields->save($record, $answers);
            if (! $record->number) {
                $record->update(['number' => 'BILL-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);
            }
            $record->items()->delete();
            $record->items()->createMany($items);
            $this->settlements->recalculate($record);
            PurchaseAudit::record($record, $bill ? 'updated' : 'created', $actorId, ['total' => $record->total, 'status' => $record->status]);

            return $record->load(['supplier', 'currency', 'items']);
        });
    }

    public function createCredit(int $companyId, ?int $actorId, array $data): SupplierCredit
    {
        $data = Validator::make($data, SupplierCreditRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($companyId, $actorId, $data): SupplierCredit {
            $supplier = PurchaseInputs::lockSupplier($companyId, $data['supplier_id']);
            $money = PurchaseInputs::money($companyId, $data);
            if (! empty($data['source_bill_id'])) {
                $bill = Bill::query()->forCompany($companyId)->lockForUpdate()->findOrFail($data['source_bill_id']);
                PurchaseInputs::ensure($bill->supplier_id == $supplier->id && $bill->currency_id == $data['currency_id'] && $bill->status === 'OPEN', 'source_bill_id', 'The source bill must be posted and belong to this supplier and currency.');
                $bill->setRelation('items', $bill->items()->lockForUpdate()->get());
                $money = $bill->only(['currency_id', 'exchange_rate']);
                $data['tax_included'] = $bill->tax_included;
                $items = $this->creditBillItems($bill, $data['items']);
                $bill->update(['financial_locked_at' => $bill->financial_locked_at ?? now()]);
            } elseif (! empty($data['source_expense_id'])) {
                $expense = Expense::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($data['source_expense_id']);
                PurchaseInputs::ensure($expense->supplier_id == $supplier->id && $expense->currency_id == $data['currency_id'], 'source_expense_id', 'Assign this expense to the same supplier and currency first.');
                $money = $expense->only(['currency_id', 'exchange_rate']);
                $data['tax_included'] = true;
                $items = [$this->creditExpense($expense, (int) $data['source_amount'])];
            } else {
                $items = $this->composeItems($companyId, $data['items'], $money['exchange_rate'], (bool) ($data['tax_included'] ?? false));
            }
            $credit = SupplierCredit::query()->create([
                ...Arr::only($data, ['supplier_id', 'reference', 'document_date', 'notes', 'tax_included', 'source_bill_id', 'source_expense_id']),
                ...$money, ...$this->totals($items), 'company_id' => $companyId, 'creator_id' => $actorId, 'status' => 'OPEN',
                'supplier_snapshot' => $supplier->only(['name', 'contact_name', 'email', 'tax_id', 'addresses']),
            ]);
            $credit->update(['number' => 'SC-'.str_pad((string) $credit->id, 6, '0', STR_PAD_LEFT)]);
            $credit->items()->createMany($items);
            PurchaseAudit::record($credit, 'recorded', $actorId, ['total' => $credit->total, 'source_bill_id' => $credit->source_bill_id, 'source_expense_id' => $credit->source_expense_id]);

            return $credit->load(['supplier', 'currency', 'items', 'allocations', 'refunds']);
        });
    }

    public function act(Bill|SupplierCredit $record, string $action, ?string $reason, ?int $actorId): void
    {
        DB::transaction(function () use ($record, $action, $reason, $actorId): void {
            PurchaseInputs::lockSupplier($record->company_id, $record->supplier_id);
            $record->refresh();
            if ($action === 'open' && $record instanceof Bill) {
                PurchaseInputs::ensure($record->status === 'DRAFT', 'action', 'Only a draft bill can be opened.');
                $record->update(['status' => 'OPEN']);
            } else {
                PurchaseInputs::ensure($action === 'void' && trim((string) $reason) !== '', 'action', 'A void reason is required.');
                if ($record->status === 'VOID') {
                    return;
                }
                if ($record instanceof Bill) {
                    PurchaseInputs::ensure(! $record->paymentAllocations()->exists() && ! $record->creditAllocations()->exists() && ! $record->credits()->where('status', '!=', 'VOID')->exists(), 'bill', 'Release settlements and void linked credits before voiding this bill.');
                } else {
                    PurchaseInputs::ensure(! $record->allocations()->exists() && ! $record->refunds()->where('status', 'OPEN')->exists(), 'credit', 'Release allocations and void refunds before voiding this credit.');
                }
                $record->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);
            }
            PurchaseAudit::record($record, $action, $actorId, ['reason' => $reason]);
        });
    }

    private function composeItems(int $companyId, array $input, float|int $rate, bool $included): array
    {
        $ids = collect($input)->flatMap(fn (array $line) => $line['tax_type_ids'] ?? [])->unique();
        $types = TaxType::query()->where('company_id', $companyId)->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES)->whereIn('id', $ids)->get()->keyBy('id');
        $lines = array_map(function (array $line) use ($types): array {
            $taxIds = $line['tax_type_ids'] ?? [];
            PurchaseInputs::ensure($line['price'] * $line['quantity'] <= 999999999999, 'items', 'The line amount exceeds the supported money range.');
            PurchaseInputs::ensure(count($taxIds) === count(array_unique($taxIds)), 'items', 'A tax can only appear once per line.');
            $line['discount_type'] = 'percentage';
            $line['taxes'] = array_map(function ($id) use ($types): array {
                $type = $types->get($id);
                PurchaseInputs::ensure($type !== null, 'items', 'Unknown purchase tax.');

                return [...$type->only(['name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']), 'tax_type_id' => $type->id];
            }, $taxIds);

            return $line;
        }, $input);
        $computed = DocumentTaxes::compose($lines, [], 0, 'percentage', true, true, $included);
        PurchaseInputs::ensure($computed['total'] > 0 && $computed['total'] <= 999999999999, 'items', 'The document total must be positive and within the supported money range.');
        $items = [];
        $used = 0;
        $base = PurchaseInputs::base($computed['total'], $rate);
        foreach ($lines as $index => $line) {
            $amounts = $computed['lines'][$index];
            $addedTax = $included ? array_sum(array_column(array_filter($amounts['taxes'], fn ($tax) => (bool) $tax['compound_tax']), 'amount')) : $amounts['tax'];
            $total = $amounts['total'] + $addedTax;
            $lineBase = ProportionalAmount::slice($base, $used, $used + $total, $computed['total']);
            // Partition the rounded line value between net cost and tax. Independent
            // conversions can otherwise round several tax rows above the line total.
            $taxPosition = $total - $amounts['tax'];
            $taxRows = [];
            foreach ($amounts['taxes'] as $tax) {
                $taxRows[] = [...$tax, 'base_amount' => ProportionalAmount::slice($lineBase, $taxPosition, $taxPosition + $tax['amount'], $total)];
                $taxPosition += $tax['amount'];
            }
            $items[] = [
                'company_id' => $companyId, ...Arr::only($line, ['description', 'expense_category_id', 'quantity', 'price', 'discount', 'discount_type']),
                'sub_total' => $amounts['sub_total'], 'discount_val' => $amounts['discount_val'], 'tax' => $amounts['tax'], 'total' => $total,
                'base_total' => $lineBase, 'taxes' => $taxRows,
            ];
            $used += $total;
        }

        return $items;
    }

    private function creditBillItems(Bill $bill, array $input): array
    {
        $items = [];
        foreach ($input as $line) {
            $source = $bill->items->firstWhere('id', $line['source_bill_item_id'] ?? null);
            PurchaseInputs::ensure($source !== null, 'items', 'Each credit line must reference a line on its source bill.');
            $previous = SupplierCreditItem::query()->where('source_bill_item_id', $source->id)->whereHas('credit', fn ($query) => $query->where('status', '!=', 'VOID'))->lockForUpdate()->get();
            $before = $previous->sum(fn ($item) => CreditNoteAmounts::toHundredths($item->quantity));
            $after = $before + CreditNoteAmounts::toHundredths($line['quantity']);
            $whole = CreditNoteAmounts::toHundredths($source->quantity);
            PurchaseInputs::ensure($after <= $whole, 'items', 'The credited quantity exceeds the remaining source quantity.');
            $item = $source->only(['company_id', 'description', 'expense_category_id', 'price', 'discount', 'discount_type']);
            $item += ['quantity' => $line['quantity'], 'source_bill_item_id' => $source->id];
            foreach (['sub_total', 'discount_val', 'tax', 'total', 'base_total'] as $key) {
                $item[$key] = ProportionalAmount::slice((int) $source->$key, $before, $after, $whole);
            }
            $item['taxes'] = array_map(fn (array $tax) => [...$tax, 'amount' => ProportionalAmount::slice((int) $tax['amount'], $before, $after, $whole), 'base_amount' => ProportionalAmount::slice((int) $tax['base_amount'], $before, $after, $whole)], $source->taxes ?? []);
            // Derive tax from the individual snapshots, preserving every last minor unit.
            $item['tax'] = array_sum(array_column($item['taxes'], 'amount'));
            $items[] = $item;
        }

        return $items;
    }

    private function creditExpense(Expense $expense, int $amount): array
    {
        $before = (int) SupplierCredit::query()->where('source_expense_id', $expense->id)->where('status', '!=', 'VOID')->lockForUpdate()->get()->sum('total');
        $after = $before + $amount;
        PurchaseInputs::ensure($after <= $expense->amount, 'source_amount', 'The credit exceeds the remaining expense amount.');
        $taxes = $expense->taxes->map(fn ($tax) => [...$tax->only(['tax_type_id', 'name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']), 'amount' => ProportionalAmount::slice((int) $tax->amount, $before, $after, (int) $expense->amount), 'base_amount' => ProportionalAmount::slice((int) $tax->base_amount, $before, $after, (int) $expense->amount)])->all();
        $tax = array_sum(array_column($taxes, 'amount'));

        return ['company_id' => $expense->company_id, 'expense_category_id' => $expense->expense_category_id, 'description' => 'Credit for expense '.($expense->expense_number ?: $expense->id), 'quantity' => 1, 'price' => $amount, 'sub_total' => $amount - $tax, 'tax' => $tax, 'total' => $amount, 'base_total' => ProportionalAmount::slice((int) $expense->base_amount, $before, $after, (int) $expense->amount), 'taxes' => $taxes];
    }

    private function totals(array $items): array
    {
        $total = array_sum(array_column($items, 'total'));
        PurchaseInputs::ensure($total > 0, 'items', 'The document must credit or charge at least one minor unit.');

        return ['sub_total' => array_sum(array_column($items, 'sub_total')), 'tax' => array_sum(array_column($items, 'tax')), 'total' => $total, 'base_total' => array_sum(array_column($items, 'base_total'))];
    }

    private function unchanged(Bill $bill, array $data, array $money): bool
    {
        $normalise = fn (array $line) => [(string) $line['description'], (int) $line['expense_category_id'], (float) $line['quantity'], (int) $line['price'], (float) ($line['discount'] ?? 0), array_map('intval', $line['tax_type_ids'] ?? array_column($line['taxes'] ?? [], 'tax_type_id'))];

        return $bill->supplier_id == $data['supplier_id'] && $bill->currency_id == $money['currency_id'] && $bill->exchange_rate == $money['exchange_rate']
            && $bill->document_date === $data['document_date'] && $bill->tax_included === (bool) ($data['tax_included'] ?? false)
            && $bill->items->map(fn ($item) => $normalise($item->toArray()))->all() === array_map($normalise, $data['items']);
    }
}
