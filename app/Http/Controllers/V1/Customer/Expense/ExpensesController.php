<?php

namespace App\Http\Controllers\V1\Customer\Expense;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ExpenseResource;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $expenses = Expense::with('category', 'creator', 'fields')
            ->whereUser(Auth::guard('customer')->id())
            ->applyFilters($request->only([
                'expense_category_id',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
            ]))
            ->paginateData($limit);

        return ExpenseResource::collection($expenses)
            ->additional(['meta' => [
                'expenseTotalCount' => Expense::whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Expense  $expense
     * @return \Illuminate\Http\Response
     */
    public function show(Company $company, $id)
    {
        $expense = $company->expenses()
            ->whereUser(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $expense) {
            return response()->json(['error' => 'expense_not_found'], 404);
        }

        return new ExpenseResource($expense);
    }
}
