<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\DuplicateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;

class DuplicateExpenseController extends Controller
{
    /**
     * Duplicate an expense, appending " (copy)" to the note (description).
     */
    public function __invoke(DuplicateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);
        $this->authorize('create', Expense::class);

        $expense->load('fields');

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));
        $currentCurrency = $expense->currency_id;
        $exchangeRate = $companyCurrency != $currentCurrency ? $expense->exchange_rate : 1;

        $notes = trim((string) $expense->notes);
        $duplicatedNotes = $notes === '' ? '(copy)' : $notes.' (copy)';

        $newExpense = Expense::query()->create([
            'expense_date' => $request->validated('expense_date'),
            'expense_number' => null,
            'expense_category_id' => $expense->expense_category_id,
            'payment_method_id' => $expense->payment_method_id,
            'amount' => $expense->amount,
            'customer_id' => $expense->customer_id,
            'notes' => $duplicatedNotes,
            'currency_id' => $expense->currency_id,
            'creator_id' => $request->user()->id,
            'company_id' => $request->header('company'),
            'exchange_rate' => $exchangeRate,
            'base_amount' => $expense->amount * $exchangeRate,
        ]);

        if ((string) $newExpense->currency_id !== (string) $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($newExpense);
        }

        if ($expense->fields()->exists()) {
            $customFields = [];

            foreach ($expense->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $newExpense->addCustomFields($customFields);
        }

        return new ExpenseResource($newExpense);
    }
}
