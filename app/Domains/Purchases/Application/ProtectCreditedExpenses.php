<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\SupplierCredit;

class ProtectCreditedExpenses
{
    public function updating(Expense $expense): void
    {
        if ($expense->isDirty(['amount', 'base_amount', 'exchange_rate', 'currency_id', 'supplier_id', 'expense_category_id', 'expense_date'])) {
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
