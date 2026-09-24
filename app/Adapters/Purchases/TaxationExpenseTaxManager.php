<?php

namespace App\Adapters\Purchases;

use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Taxation\Models\TaxType;
use App\Support\MoneyConversion;

class TaxationExpenseTaxManager implements ExpenseTaxManager
{
    public function replace(Expense $expense, array $taxes): void
    {
        $this->clear($expense);

        if ($taxes === []) {
            return;
        }

        $taxTypes = TaxType::query()
            ->where('company_id', $expense->company_id)
            ->where('type', TaxType::TYPE_GENERAL)
            ->whereTransactionType(TaxType::TRANSACTION_TYPE_PURCHASES)
            ->whereIn('id', collect($taxes)->pluck('tax_type_id'))
            ->get()
            ->keyBy('id');

        foreach ($taxes as $tax) {
            $taxType = $taxTypes->get($tax['tax_type_id']);

            $expense->taxes()->create([
                'tax_type_id' => $taxType->id,
                'company_id' => $expense->company_id,
                'currency_id' => $expense->currency_id,
                'exchange_rate' => $expense->exchange_rate,
                'amount' => (int) $tax['amount'],
                'base_amount' => MoneyConversion::toBaseMinor($tax['amount'], $expense->exchange_rate),
                'name' => $taxType->name,
                'percent' => $taxType->percent,
                'fixed_amount' => $taxType->fixed_amount,
                'calculation_type' => $taxType->calculation_type,
                'compound_tax' => $taxType->compound_tax,
            ]);
        }
    }

    public function clear(Expense $expense): void
    {
        $expense->taxes()->delete();
    }
}
