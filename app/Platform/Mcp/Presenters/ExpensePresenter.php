<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Purchases\Models\Expense;

final class ExpensePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'number' => $expense->expense_number,
            'date' => Date::of($expense, 'expense_date'),
            'category' => $expense->category ? ['id' => $expense->category->id, 'name' => $expense->category->name] : null,
            'amount' => Money::of($expense->amount, $expense->currency_id),
            'customer' => $expense->customer ? ['id' => $expense->customer->id, 'name' => $expense->customer->name] : null,
            'method' => $expense->paymentMethod?->name,
            'notes' => Text::plain($expense->notes),
            'has_receipt' => $expense->getMedia('receipts')->isNotEmpty(),
            'app_url' => Link::to("expenses/{$expense->id}/edit"),
        ];
    }
}
