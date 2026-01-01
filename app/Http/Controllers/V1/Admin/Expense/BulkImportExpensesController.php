<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulkImportExpensesController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:jpg,png,pdf,doc,docx,xls,xlsx,ppt,pptx|max:20000',
            'categories' => 'nullable|array',
        ]);

        $companyId = $request->header('company');
        $currencyId = CompanySetting::getSetting('currency', $companyId);
        
        // Get the 'Unverified' category or create it
        $defaultCategory = ExpenseCategory::where('company_id', $companyId)
            ->where('name', 'Unverified')
            ->first();
        
        if (!$defaultCategory) {
             $defaultCategory = ExpenseCategory::create([
                 'name' => 'Unverified',
                 'company_id' => $companyId,
             ]);
        }
        
        $defaultCategoryId = $defaultCategory->id;
        $categories = $request->input('categories', []);

        $expenses = [];

        DB::transaction(function () use ($request, $companyId, $currencyId, $defaultCategoryId, $categories, &$expenses) {
            foreach ($request->file('files') as $index => $file) {
                $currentCategoryId = isset($categories[$index]) && $categories[$index] ? $categories[$index] : $defaultCategoryId;

                $expense = Expense::create([
                    'expense_date' => Carbon::now()->format('Y-m-d'),
                    'expense_category_id' => $currentCategoryId,
                    'amount' => 0,
                    'currency_id' => $currencyId,
                    'company_id' => $companyId,
                    'creator_id' => $request->user()->id,
                    'exchange_rate' => 1,
                    'base_amount' => 0,
                    'notes' => 'Imported from bulk upload',
                ]);

                $expense->addMedia($file)->toMediaCollection('receipts');
                
                $expenses[] = $expense;
            }
        });

        return response()->json([
            'success' => true,
            'count' => count($expenses),
            'message' => count($expenses) . ' receipts imported successfully.'
        ]);
    }
}
