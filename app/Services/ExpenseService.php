<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseService
{
    public function create(Request $request): Expense
    {
        $expense = Expense::create($request->getExpensePayload());

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $expense['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if ($request->hasFile('attachment_receipt')) {
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->addCustomFields(json_decode($request->customFields));
        }

        return $expense;
    }

    public function update(Expense $expense, Request $request): bool
    {
        $data = $request->getExpensePayload();

        $expense->update($data);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if (isset($request->is_attachment_receipt_removed) && (bool) $request->is_attachment_receipt_removed) {
            $expense->clearMediaCollection('receipts');
        }
        if ($request->hasFile('attachment_receipt')) {
            $expense->clearMediaCollection('receipts');
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->updateCustomFields(json_decode($request->customFields));
        }

        return true;
    }
}
