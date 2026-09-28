<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Contracts\DocumentNumberAssigner;
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

/**
 * Bills and supplier credits: their lines, taxes and totals, worked out on
 * the server from the submitted lines and the company's purchase taxes.
 */
class PurchaseDocumentService
{
    /** The most a line or a document may come to, in minor units. */
    private const MONEY_LIMIT = 999999999999;

    /** What a document keeps of its supplier as it was when it was recorded. */
    private const SUPPLIER_SNAPSHOT = ['name', 'contact_name', 'email', 'tax_id', 'addresses'];

    public function __construct(
        private readonly SupplierSettlementService $settlements,
        private readonly PurchaseCustomFields $customFields,
        private readonly DocumentNumberAssigner $numbers,
    ) {}

    /**
     * Create a bill, or change one that is not void.
     *
     * Once a bill is settled or credited its financial details are locked:
     * only the reference, due date, notes and custom fields can change.
     */
    public function saveBill(?Bill $bill, int $companyId, ?int $actorId, array $data): Bill
    {
        $data = Validator::make($data, BillRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($bill, $companyId, $actorId, $data): Bill {
            // Changing an unsettled bill's supplier locks both contacts in a stable order.
            $suppliers = Supplier::query()
                ->forCompany($companyId)
                ->whereIn('id', array_filter([$data['supplier_id'], $bill?->supplier_id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $supplier = $suppliers->get($data['supplier_id']);
            PurchaseInputs::ensure($supplier !== null, 'supplier_id', 'Supplier not found.');
            PurchaseInputs::ensure($supplier->enabled, 'supplier_id', 'This supplier is inactive.');

            $money = PurchaseInputs::money($companyId, $data);
            $record = $bill
                ? Bill::query()->forCompany($companyId)->lockForUpdate()->findOrFail($bill->id)
                : new Bill(['company_id' => $companyId, 'creator_id' => $actorId]);
            $answers = $this->customFields->resolve($companyId, 'Bill', $data['customFields'] ?? [], $this->customFields->saved($record));

            if ($bill) {
                PurchaseInputs::ensure($record->status !== 'VOID', 'bill', 'A void bill cannot be edited.');

                if ($record->financial_locked_at) {
                    PurchaseInputs::ensure(
                        $this->unchanged($record, $data, $money),
                        'items',
                        'Financial details are locked after settlement or credit. Use a supplier credit to adjust this bill.',
                    );
                    $record->update(Arr::only($data, ['reference', 'due_date', 'notes']));
                    $this->customFields->save($record, $answers);

                    return $record->load(['supplier', 'currency', 'items']);
                }
            }

            $items = $this->composeItems($companyId, $data['items'], $money['exchange_rate'], (bool) ($data['tax_included'] ?? false));

            $record->fill([
                ...Arr::only($data, ['supplier_id', 'reference', 'document_date', 'due_date', 'notes', 'tax_included']),
                ...$money,
                ...$this->totals($items),
                'status' => $bill && $record->status === 'OPEN' ? 'OPEN' : ($data['status'] ?? 'DRAFT'),
                'supplier_snapshot' => $supplier->only(self::SUPPLIER_SNAPSHOT),
            ]);

            if (! $record->number) {
                $record->fill($this->numbers->next(Bill::class, $companyId));
            }

            $record->save();
            $this->customFields->save($record, $answers);
            $record->items()->delete();
            $record->items()->createMany($items);
            $this->settlements->recalculate($record);

            return $record->load(['supplier', 'currency', 'items']);
        });
    }

    /**
     * Record a supplier credit: against lines of a posted bill (at the bill's
     * prices, taxes and rate), against part of an expense, or on its own lines.
     */
    public function createCredit(int $companyId, ?int $actorId, array $data): SupplierCredit
    {
        $data = Validator::make($data, SupplierCreditRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($companyId, $actorId, $data): SupplierCredit {
            $supplier = PurchaseInputs::lockSupplier($companyId, $data['supplier_id']);
            $money = PurchaseInputs::money($companyId, $data);

            if (! empty($data['source_bill_id'])) {
                $bill = $this->lockSourceBill($companyId, $supplier, $data);
                $money = $bill->only(['currency_id', 'exchange_rate']);
                $data['tax_included'] = $bill->tax_included;
                $items = $this->creditBillItems($bill, $data['items']);
                $bill->update(['financial_locked_at' => $bill->financial_locked_at ?? now()]);
            } elseif (! empty($data['source_expense_id'])) {
                $expense = $this->lockSourceExpense($companyId, $supplier, $data);
                $money = PurchaseInputs::money($companyId, $expense->only(['currency_id', 'exchange_rate']));
                $data['tax_included'] = true;
                $items = [$this->creditExpense($expense, (int) $data['source_amount'])];
            } else {
                $items = $this->composeItems($companyId, $data['items'], $money['exchange_rate'], (bool) ($data['tax_included'] ?? false));
            }

            $credit = SupplierCredit::query()->create([
                ...Arr::only($data, ['supplier_id', 'reference', 'document_date', 'notes', 'tax_included', 'source_bill_id', 'source_expense_id']),
                ...$money,
                ...$this->totals($items),
                'company_id' => $companyId,
                'creator_id' => $actorId,
                'status' => 'OPEN',
                'supplier_snapshot' => $supplier->only(self::SUPPLIER_SNAPSHOT),
                ...$this->numbers->next(SupplierCredit::class, $companyId),
            ]);
            $credit->items()->createMany($items);

            return $credit->load(['supplier', 'currency', 'items', 'allocations', 'refunds']);
        });
    }

    /**
     * Open a draft bill, or void a bill or credit with a reason. A document
     * that still settles or is settled by something cannot be voided.
     */
    public function act(Bill|SupplierCredit $record, string $action, ?string $reason): void
    {
        DB::transaction(function () use ($record, $action, $reason): void {
            PurchaseInputs::lockSupplier($record->company_id, $record->supplier_id);
            $record->refresh();

            if ($action === 'open' && $record instanceof Bill) {
                PurchaseInputs::ensure($record->status === 'DRAFT', 'action', 'Only a draft bill can be opened.');
                $record->update(['status' => 'OPEN']);

                return;
            }

            PurchaseInputs::ensure($action === 'void' && trim((string) $reason) !== '', 'action', 'A void reason is required.');

            if ($record->status === 'VOID') {
                return;
            }

            if ($record instanceof Bill) {
                PurchaseInputs::ensure(
                    ! $record->paymentAllocations()->exists()
                        && ! $record->creditAllocations()->exists()
                        && ! $record->credits()->where('status', '!=', 'VOID')->exists(),
                    'bill',
                    'Release settlements and void linked credits before voiding this bill.',
                );
            } else {
                PurchaseInputs::ensure(
                    ! $record->allocations()->exists() && ! $record->refunds()->where('status', 'OPEN')->exists(),
                    'credit',
                    'Release allocations and void refunds before voiding this credit.',
                );
            }

            $record->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);
        });
    }

    private function lockSourceBill(int $companyId, Supplier $supplier, array $data): Bill
    {
        $bill = Bill::query()->forCompany($companyId)->lockForUpdate()->findOrFail($data['source_bill_id']);

        PurchaseInputs::ensure(
            $bill->supplier_id == $supplier->id && $bill->currency_id == $data['currency_id'] && $bill->status === 'OPEN',
            'source_bill_id',
            'The source bill must be posted and belong to this supplier and currency.',
        );

        $bill->setRelation('items', $bill->items()->lockForUpdate()->get());

        return $bill;
    }

    private function lockSourceExpense(int $companyId, Supplier $supplier, array $data): Expense
    {
        $expense = Expense::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($data['source_expense_id']);

        PurchaseInputs::ensure(
            $expense->supplier_id == $supplier->id && $expense->currency_id == $data['currency_id'],
            'source_expense_id',
            'Assign this expense to the same supplier and currency first.',
        );

        // Older expenses may have no stored rate; one in the company currency is at 1.
        PurchaseInputs::ensure(
            $expense->exchange_rate !== null || (int) $expense->currency_id === PurchaseInputs::companyCurrency($companyId),
            'source_expense_id',
            'Set the exchange rate on this expense before crediting it.',
        );

        return $expense;
    }

    /**
     * Turn submitted lines into stored lines: the company's purchase taxes
     * applied, amounts computed as the document forms compute them, and the
     * base-currency total split across the lines and their taxes.
     */
    private function composeItems(int $companyId, array $input, float|int $rate, bool $included): array
    {
        $lines = $this->withTaxes($companyId, $input);
        $computed = DocumentTaxes::compose($lines, [], 0, 'percentage', true, true, $included);

        PurchaseInputs::ensure(
            $computed['total'] > 0 && $computed['total'] <= self::MONEY_LIMIT,
            'items',
            'The document total must be positive and within the supported money range.',
        );

        $items = [];
        $used = 0;
        $base = PurchaseInputs::base($computed['total'], $rate);

        foreach ($lines as $index => $line) {
            $amounts = $computed['lines'][$index];
            $addedTax = $included ? $this->compoundTax($amounts['taxes']) : $amounts['tax'];
            $total = $amounts['total'] + $addedTax;
            $lineBase = ProportionalAmount::slice($base, $used, $used + $total, $computed['total']);

            $items[] = [
                'company_id' => $companyId,
                ...Arr::only($line, ['description', 'expense_category_id', 'quantity', 'price', 'discount', 'discount_type']),
                'sub_total' => $amounts['sub_total'],
                'discount_val' => $amounts['discount_val'],
                'tax' => $amounts['tax'],
                'total' => $total,
                'base_total' => $lineBase,
                'taxes' => $this->taxRows($amounts, $total, $lineBase),
            ];

            $used += $total;
        }

        return $items;
    }

    /**
     * The submitted lines with each named purchase tax attached as a snapshot.
     */
    private function withTaxes(int $companyId, array $input): array
    {
        $types = TaxType::query()
            ->where('company_id', $companyId)
            ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES)
            ->whereIn('id', collect($input)->flatMap(fn (array $line) => $line['tax_type_ids'] ?? [])->unique())
            ->get()
            ->keyBy('id');

        return array_map(function (array $line) use ($types): array {
            $taxIds = $line['tax_type_ids'] ?? [];

            PurchaseInputs::ensure($line['price'] * $line['quantity'] <= self::MONEY_LIMIT, 'items', 'The line amount exceeds the supported money range.');
            PurchaseInputs::ensure(count($taxIds) === count(array_unique($taxIds)), 'items', 'A tax can only appear once per line.');

            $line['discount_type'] = 'percentage';
            $line['taxes'] = array_map(function ($id) use ($types): array {
                $type = $types->get($id);
                PurchaseInputs::ensure($type !== null, 'items', 'Unknown purchase tax.');

                return [
                    ...$type->only(['name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']),
                    'tax_type_id' => $type->id,
                ];
            }, $taxIds);

            return $line;
        }, $input);
    }

    /**
     * The compound taxes of a tax-inclusive line, which come on top of its price.
     */
    private function compoundTax(array $taxes): int
    {
        $compound = array_filter($taxes, fn ($tax) => (bool) $tax['compound_tax']);

        return array_sum(array_column($compound, 'amount'));
    }

    /**
     * A line's tax rows with their share of the line's base amount.
     *
     * The rounded line value is partitioned between net cost and tax.
     * Independent conversions could otherwise round several tax rows above the
     * line total.
     */
    private function taxRows(array $amounts, int $total, int $lineBase): array
    {
        $rows = [];
        $position = $total - $amounts['tax'];

        foreach ($amounts['taxes'] as $tax) {
            $rows[] = [
                ...$tax,
                'base_amount' => ProportionalAmount::slice($lineBase, $position, $position + $tax['amount'], $total),
            ];
            $position += $tax['amount'];
        }

        return $rows;
    }

    /**
     * Credit lines for part of a bill's lines: each credited quantity takes
     * the matching slice of its source line's amounts and taxes, after what
     * earlier credits already took, so partial credits add up exactly.
     */
    private function creditBillItems(Bill $bill, array $input): array
    {
        $items = [];

        foreach ($input as $line) {
            $source = $bill->items->firstWhere('id', $line['source_bill_item_id'] ?? null);
            PurchaseInputs::ensure($source !== null, 'items', 'Each credit line must reference a line on its source bill.');

            $previous = SupplierCreditItem::query()
                ->where('source_bill_item_id', $source->id)
                ->whereHas('credit', fn ($query) => $query->where('status', '!=', 'VOID'))
                ->lockForUpdate()
                ->get();
            $before = $previous->sum(fn ($item) => CreditNoteAmounts::toHundredths($item->quantity));
            $after = $before + CreditNoteAmounts::toHundredths($line['quantity']);
            $whole = CreditNoteAmounts::toHundredths($source->quantity);
            PurchaseInputs::ensure($after <= $whole, 'items', 'The credited quantity exceeds the remaining source quantity.');

            $item = $source->only(['company_id', 'description', 'expense_category_id', 'price', 'discount', 'discount_type']);
            $item += ['quantity' => $line['quantity'], 'source_bill_item_id' => $source->id];

            foreach (['sub_total', 'discount_val', 'tax', 'total', 'base_total'] as $key) {
                $item[$key] = ProportionalAmount::slice((int) $source->$key, $before, $after, $whole);
            }

            $item['taxes'] = array_map(fn (array $tax) => [
                ...$tax,
                'amount' => ProportionalAmount::slice((int) $tax['amount'], $before, $after, $whole),
                'base_amount' => ProportionalAmount::slice((int) $tax['base_amount'], $before, $after, $whole),
            ], $source->taxes ?? []);

            // Derive tax from the individual snapshots, preserving every last minor unit.
            $item['tax'] = array_sum(array_column($item['taxes'], 'amount'));
            $items[] = $item;
        }

        return $items;
    }

    /**
     * The single credit line for part of an expense, with the matching slice
     * of its taxes after what earlier credits already took.
     */
    private function creditExpense(Expense $expense, int $amount): array
    {
        $whole = (int) $expense->amount;
        $before = (int) SupplierCredit::query()
            ->where('source_expense_id', $expense->id)
            ->where('status', '!=', 'VOID')
            ->lockForUpdate()
            ->get()
            ->sum('total');
        $after = $before + $amount;
        PurchaseInputs::ensure($after <= $expense->amount, 'source_amount', 'The credit exceeds the remaining expense amount.');

        $taxes = $expense->taxes->map(fn ($tax) => [
            ...$tax->only(['tax_type_id', 'name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']),
            'amount' => ProportionalAmount::slice((int) $tax->amount, $before, $after, $whole),
            'base_amount' => ProportionalAmount::slice((int) $tax->base_amount, $before, $after, $whole),
        ])->all();
        $tax = array_sum(array_column($taxes, 'amount'));

        return [
            'company_id' => $expense->company_id,
            'expense_category_id' => $expense->expense_category_id,
            'description' => 'Credit for expense '.($expense->expense_number ?: $expense->id),
            'quantity' => 1,
            'price' => $amount,
            'sub_total' => $amount - $tax,
            'tax' => $tax,
            'total' => $amount,
            'base_total' => ProportionalAmount::slice((int) $expense->base_amount, $before, $after, $whole),
            'taxes' => $taxes,
        ];
    }

    private function totals(array $items): array
    {
        $total = array_sum(array_column($items, 'total'));
        PurchaseInputs::ensure($total > 0, 'items', 'The document must credit or charge at least one minor unit.');

        return [
            'sub_total' => array_sum(array_column($items, 'sub_total')),
            'tax' => array_sum(array_column($items, 'tax')),
            'total' => $total,
            'base_total' => array_sum(array_column($items, 'base_total')),
        ];
    }

    /**
     * Whether a submitted bill carries the same financial details as the
     * stored one, so a locked bill can still take other edits.
     */
    private function unchanged(Bill $bill, array $data, array $money): bool
    {
        $normalise = fn (array $line) => [
            (string) $line['description'],
            (int) $line['expense_category_id'],
            (float) $line['quantity'],
            (int) $line['price'],
            (float) ($line['discount'] ?? 0),
            array_map('intval', $line['tax_type_ids'] ?? array_column($line['taxes'] ?? [], 'tax_type_id')),
        ];

        return $bill->supplier_id == $data['supplier_id']
            && $bill->currency_id == $money['currency_id']
            && $bill->exchange_rate == $money['exchange_rate']
            && $bill->document_date === $data['document_date']
            && $bill->tax_included === (bool) ($data['tax_included'] ?? false)
            && $bill->items->map(fn ($item) => $normalise($item->toArray()))->all() === array_map($normalise, $data['items']);
    }
}
