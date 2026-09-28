<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Purchases\Contracts\ExpenseExchangeRateRecorder;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly ExpenseTaxManager $expenseTaxManager,
        private readonly ExpenseExchangeRateRecorder $expenseExchangeRateRecorder,
        private readonly ExpenseReceiptManager $expenseReceiptManager,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{tax_type_id: int, amount: int}>|null  $taxes
     */
    public function create(
        array $attributes,
        ?array $taxes = null,
        ?PendingExpenseReceipt $receipt = null,
        ?iterable $customFields = null,
    ): Expense {
        $expense = DB::transaction(function () use ($attributes, $taxes): Expense {
            $expense = Expense::create($attributes);

            if ($taxes !== null) {
                $this->expenseTaxManager->replace($expense, $taxes);
            }

            return $expense;
        });

        $companyCurrency = CompanySetting::getSetting('currency', $expense->company_id);

        if ((string) $expense['currency_id'] !== $companyCurrency) {
            $this->expenseExchangeRateRecorder->record($expense);
        }

        if ($receipt) {
            $this->expenseReceiptManager->attach($expense, $receipt);
        }

        if ($customFields) {
            $this->customFieldValueWriter->attach($expense, $customFields);
        }

        return $expense->load('taxes.taxType');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{tax_type_id: int, amount: int}>|null  $taxes
     */
    public function update(
        Expense $expense,
        array $attributes,
        ?array $taxes = null,
        ?PendingExpenseReceipt $receipt = null,
        bool $removeReceipt = false,
        ?iterable $customFields = null,
    ): Expense {
        DB::transaction(function () use ($expense, $attributes, $taxes): void {
            if ($expense->supplier_id || ($attributes['supplier_id'] ?? null)) {
                Supplier::query()
                    ->where('company_id', $expense->company_id)
                    ->whereIn('id', array_filter([$expense->supplier_id, $attributes['supplier_id'] ?? null]))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $expense->setRawAttributes($locked->getAttributes(), true);

            if ($taxes !== null && SupplierCredit::query()->where('source_expense_id', $expense->id)->exists()) {
                $old = $expense->taxes()
                    ->get()
                    ->map(fn ($tax) => ['tax_type_id' => (int) $tax->tax_type_id, 'amount' => (int) $tax->amount])
                    ->sortBy('tax_type_id')
                    ->values()
                    ->all();

                $new = collect($taxes)
                    ->map(fn ($tax) => ['tax_type_id' => (int) $tax['tax_type_id'], 'amount' => (int) $tax['amount']])
                    ->sortBy('tax_type_id')
                    ->values()
                    ->all();

                PurchaseInputs::ensure(
                    $old === $new,
                    'taxes',
                    'purchase_credited_expense_taxes_locked',
                );

                $taxes = null;
            }

            $expense->update($attributes);

            if ($taxes !== null) {
                $this->expenseTaxManager->replace($expense, $taxes);
            }
        });

        $companyCurrency = CompanySetting::getSetting('currency', $expense->company_id);

        if ((string) $attributes['currency_id'] !== $companyCurrency) {
            $this->expenseExchangeRateRecorder->record($expense);
        }

        if ($removeReceipt) {
            $this->expenseReceiptManager->clear($expense);
        }

        if ($receipt) {
            $this->expenseReceiptManager->replace($expense, $receipt);
        }

        if ($customFields) {
            $this->customFieldValueWriter->update($expense, $customFields);
        }

        return $expense->fresh('taxes.taxType');
    }

    /**
     * Delete a batch of one company's expenses. The suppliers involved are
     * locked first, in id order, so a delete cannot race a supplier credit
     * being raised against the same expense.
     */
    public function delete(array $ids, int $companyId): void
    {
        $suppliers = Expense::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->whereNotNull('supplier_id')
            ->pluck('supplier_id')
            ->unique();

        DB::transaction(function () use ($ids, $companyId, $suppliers): void {
            Supplier::query()
                ->forCompany($companyId)
                ->whereIn('id', $suppliers)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $expenses = Expense::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($expenses as $expense) {
                $expense->delete();
            }
        });
    }
}
