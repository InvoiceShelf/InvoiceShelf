<?php

namespace App\Http\Controllers\V1\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];

        $i = 0;
        $months = [];
        $monthCounter = 0;
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = intval($terms[0]);

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
            $end->month($companyStartMonth)->endOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
            $end->subYear()->month($companyStartMonth)->endOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        // Determine if active filter is enabled
        $activeOnly = $request->boolean('active_only', false);

        while ($monthCounter < 12) {
            // Build invoice query with optional active filter
            $invoiceQuery = Invoice::whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )->whereCompany();

            if ($activeOnly) {
                $invoiceQuery->whereIn('status', [
                    Invoice::STATUS_SENT,
                    Invoice::STATUS_VIEWED,
                ])->whereIn('paid_status', [
                    Invoice::STATUS_UNPAID,
                    Invoice::STATUS_PARTIALLY_PAID,
                ]);
            }

            array_push($invoice_totals, $invoiceQuery->sum('base_total'));

            array_push(
                $expense_totals,
                Expense::whereBetween(
                    'expense_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->sum('base_amount')
            );
            array_push(
                $receipt_totals,
                Payment::whereBetween(
                    'payment_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->sum('base_amount')
            );
            array_push(
                $net_income_totals,
                ($receipt_totals[$i] - $expense_totals[$i])
            );
            $i++;
            array_push($months, $start->translatedFormat('M'));
            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        // Build total sales query with optional active filter
        $totalSalesQuery = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )->whereCompany();

        if ($activeOnly) {
            $totalSalesQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }

        $total_sales = $totalSalesQuery->sum('base_total');

        $total_receipts = Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        $total_expenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        $total_net_income = (int) $total_receipts - (int) $total_expenses;

        $chart_data = [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
        ];

        // Build customer count query with optional active filter
        $customerQuery = Customer::whereCompany();
        if ($activeOnly) {
            $customerQuery->where('enable_portal', true)
                ->where(function ($query) {
                    $query->whereHas('invoices', function ($subQuery) {
                        $subQuery->whereIn('status', [
                            Invoice::STATUS_SENT,
                            Invoice::STATUS_VIEWED,
                        ])->whereIn('paid_status', [
                            Invoice::STATUS_UNPAID,
                            Invoice::STATUS_PARTIALLY_PAID,
                        ]);
                    })->orWhereHas('estimates', function ($subQuery) {
                        $subQuery->whereIn('status', [
                            Estimate::STATUS_SENT,
                            Estimate::STATUS_VIEWED,
                        ]);
                    });
                });
        }
        $total_customer_count = $customerQuery->count();

        // Build invoice count query with optional active filter
        $invoiceCountQuery = Invoice::whereCompany();
        if ($activeOnly) {
            $invoiceCountQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }
        $total_invoice_count = $invoiceCountQuery->count();

        // Build estimate count query with optional active filter
        $estimateCountQuery = Estimate::whereCompany();
        if ($activeOnly) {
            $estimateCountQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }
        $total_estimate_count = $estimateCountQuery->count();

        // Build amount due query with optional active filter
        $amountDueQuery = Invoice::whereCompany();
        if ($activeOnly) {
            $amountDueQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }
        $total_amount_due = $amountDueQuery->sum('base_due_amount');

        // Build recent due invoices query with optional active filter
        $recentDueInvoicesQuery = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0);

        if ($activeOnly) {
            $recentDueInvoicesQuery->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_VIEWED,
            ])->whereIn('paid_status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_PARTIALLY_PAID,
            ]);
        }

        $recent_due_invoices = $recentDueInvoicesQuery->take(5)->latest()->get();

        // Build recent estimates query with optional active filter
        $recentEstimatesQuery = Estimate::with('customer')->whereCompany();
        if ($activeOnly) {
            $recentEstimatesQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }
        $recent_estimates = $recentEstimatesQuery->take(5)->latest()->get();

        // Status Distribution
        $paid_count = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_PAID)->count();
        $overdue_count = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_UNPAID)
            ->where('due_date', '<', Carbon::now())
            ->count();
        $pending_count = Invoice::whereCompany()->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->where('due_date', '>=', Carbon::now())
            ->count();

        // Note: The 'filter_by' parameter from the frontend tabs has been deprecated.
        // No backend filtering logic is applied here for 'overdue', 'paid', or 'unpaid' statuses
        // as the UI has been updated to remove these tabs.

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
            'status_distribution' => [
                'paid' => $paid_count,
                'overdue' => $overdue_count,
                'pending' => $pending_count,
            ],

            // Include filter state in response for debugging
            'active_filter_applied' => $activeOnly,
        ]);
    }

    /**
     * Get top 5 outstanding invoices by client or product.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function topOutstanding(Request $request)
    {
        $request->validate([
            'type' => 'required|in:clients,products',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $company = Company::find($request->header('company'));
        $this->authorize('view dashboard', $company);

        $type = $request->input('type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $baseQuery = Invoice::whereCompany()
            ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if ($type === 'clients') {
            $results = $baseQuery
                ->join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->select(
                    'customers.name as label',
                    DB::raw('SUM(invoices.base_due_amount) as value')
                )
                ->groupBy('customers.name')
                ->orderByDesc(DB::raw('SUM(invoices.base_due_amount)'))
                ->limit(5)
                ->get();
        } else { // products
            $results = $baseQuery
                ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->join('items', 'invoice_items.item_id', '=', 'items.id')
                ->select(
                    'items.name as label',
                    DB::raw('SUM(invoice_items.base_total) as value')
                )
                ->groupBy('items.name')
                ->orderByDesc(DB::raw('SUM(invoice_items.base_total)'))
                ->limit(5)
                ->get();
        }

        return response()->json($results);
    }

    /**
     * Get cash flow analysis data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cashFlow(Request $request)
    {
        $request->validate([
            'period_months' => 'required|integer|in:6,9,15',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $company = Company::find($request->header('company'));
        $this->authorize('view dashboard', $company);

        $periodMonths = $request->input('period_months');
        $customerId = $request->input('customer_id');

        $today = Carbon::today();
        $pastMonths = 3;
        $futureMonths = $periodMonths - $pastMonths;

        $startDate = $today->copy()->subMonths($pastMonths)->startOfMonth();
        $endDate = $today->copy()->addMonths($futureMonths)->endOfMonth();

        $paymentQuery = Payment::whereCompany()
            ->whereBetween('payment_date', [$startDate, $endDate]);

        if ($customerId) {
            $paymentQuery->where('customer_id', $customerId);
        }

        $monthlyRealIncome = $paymentQuery->select(DB::raw("strftime('%Y-%m', payment_date) as month"), DB::raw('SUM(base_amount) as total'))->groupBy('month')->get()->keyBy('month');

        $projectedIncomeQuery = Invoice::whereCompany()
            ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->whereBetween('due_date', [$startDate, $endDate]);

        if ($customerId) {
            $projectedIncomeQuery->where('customer_id', $customerId);
        }

        $monthlyProjectedIncome = $projectedIncomeQuery->select(DB::raw("strftime('%Y-%m', due_date) as month"), DB::raw('SUM(base_due_amount) as total'))->groupBy('month')->get()->keyBy('month');

        $monthlyExpenses = collect();
        if (! $customerId) {
            $monthlyExpenses = Expense::whereCompany()
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->select(DB::raw("strftime('%Y-%m', expense_date) as month"), DB::raw('SUM(base_amount) as total'))
                ->groupBy('month')
                ->get()
                ->keyBy('month');
        }

        $cashFlowData = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $monthKey = $currentDate->format('Y-m');
            $isPast = $currentDate->isPast() && ! $currentDate->isSameMonth($today);
            $isCurrent = $currentDate->isSameMonth($today);

            $realIncome = $monthlyRealIncome->get($monthKey)->total ?? 0;
            $projectedIncome = $monthlyProjectedIncome->get($monthKey)->total ?? 0;
            $expenses = $monthlyExpenses->get($monthKey)->total ?? 0;

            $dataPoint = [
                'date' => $currentDate->toDateString(),
                'realIncome' => ($isPast || $isCurrent) ? $realIncome : null,
                'projectedIncome' => (! $isPast) ? $projectedIncome : null,
                'expenses' => $customerId ? null : $expenses,
            ];

            $income = $dataPoint['realIncome'] ?? $dataPoint['projectedIncome'] ?? 0;
            $dataPoint['netCashFlow'] = $customerId ? null : ($income - $dataPoint['expenses']);

            $cashFlowData[] = $dataPoint;
            $currentDate->addMonth();
        }

        return response()->json($cashFlowData);
    }
}
