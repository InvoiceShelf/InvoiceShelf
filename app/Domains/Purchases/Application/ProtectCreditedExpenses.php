<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\SupplierCredit;

/**
 * Keeps the financial details of an expense that a supplier credit was taken
 * from, since the credit's amounts and taxes were sliced from them.
 */
class ProtectCreditedExpenses
{
    private const FINANCIAL = ['amount', 'base_amount', 'exchange_rate', 'currency_id', 'supplier_id', 'expense_category_id', 'expense_date'];

    public function updating(Expense $expense): void
    {
        $changed = array_intersect(self::FINANCIAL, array_keys($expense->getDirty()));

        // Saving an older expense fills in the rate of 1 it never stored; that changes nothing.
        if ($expense->getOriginal('exchange_rate') === null && (float) $expense->exchange_rate === 1.0) {
            $changed = array_diff($changed, ['exchange_rate']);
        }

        if ($changed !== []) {
            $this->check($expense);
        }
    }

    public function deleting(Expense $expense): void
    {
        $this->check($expense);
    }

    private function check(Expense $expense): void
    {
        PurchaseInputs::ensure(! SupplierCredit::query()->where('source_expense_id', $expense->id)->exists(), 'expense', 'An expense with supplier credits must retain its original financial details.');
    }
}
