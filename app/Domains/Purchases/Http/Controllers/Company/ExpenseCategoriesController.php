<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\RecurringTemplates;
use App\Domains\Purchases\Http\Requests\ExpenseCategoryRequest;
use App\Domains\Purchases\Http\Resources\ExpenseCategoryResource;
use App\Domains\Purchases\Models\BillItem;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCreditItem;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

class ExpenseCategoriesController extends Controller
{
    /**
     * Newest-first, searchable list of the company's categories.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $filters = $request->all();

        $categories = ExpenseCategory::applyFilters($filters)
            ->whereCompany()
            ->latest()
            ->paginateData($request->input('limit', 5));

        return ExpenseCategoryResource::collection($categories);
    }

    /**
     * Add a category to the active company.
     */
    public function store(ExpenseCategoryRequest $request)
    {
        $this->authorize('create', ExpenseCategory::class);

        $category = ExpenseCategory::create($request->getExpenseCategoryPayload());

        return new ExpenseCategoryResource($category);
    }

    /**
     * Return one category.
     */
    public function show(ExpenseCategory $category)
    {
        $this->authorize('view', $category);

        return new ExpenseCategoryResource($category);
    }

    /**
     * Save the submitted changes on a category.
     */
    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category)
    {
        $this->authorize('update', $category);

        $category->update($request->getExpenseCategoryPayload());

        return new ExpenseCategoryResource($category);
    }

    /**
     * Drop a category, unless expenses still point at it.
     */
    public function destroy(ExpenseCategory $category)
    {
        $this->authorize('delete', $category);

        $usage = $category->expenses();

        if ($usage && $usage->count() > 0) {
            return respondJson('expense_attached', 'Expense Attached');
        }

        if (BillItem::query()->where('expense_category_id', $category->id)->exists()
            || SupplierCreditItem::query()->where('expense_category_id', $category->id)->exists()
            || Supplier::query()->where('expense_category_id', $category->id)->exists()
            || RecurringTemplates::useCategory($category->company_id, $category->id)) {
            return respondJson('expense_attached', 'Expense Attached');
        }

        $category->delete();

        return response()->json(['success' => true]);
    }
}
