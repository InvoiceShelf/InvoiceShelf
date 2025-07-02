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
        $request->validate([
            'date_range' => 'nullable|string|in:all_time,last_7_days,last_30_days,last_90_days,last_12_months,custom',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        $invoice_totals = [];
        $expense_totals = [];
        $receipt_totals = [];
        $net_income_totals = [];

        $i = 0;
        $months = [];
        $monthCounter = 0;
        // Handle unified date filtering
        $dateRange = $this->getDateRange($request);
        $unifiedStartDate = $dateRange['start'];
        $unifiedEndDate = $dateRange['end'];
        $hasUnifiedFilter = $request->filled(['start_date', 'end_date']) ||
                           ($request->filled('date_range') && $request->get('date_range') !== 'all_time');

        // Determine if active filter is enabled
        $activeOnly = $request->boolean('active_only', false);

        if ($hasUnifiedFilter) {
            // Use unified date range for all calculations
            $calculationStartDate = $unifiedStartDate;
            $calculationEndDate = $unifiedEndDate;

            // For chart data with unified filter, create monthly breakdowns within the selected range
            $diffInMonths = $calculationStartDate->diffInMonths($calculationEndDate);
            $monthsToShow = min(12, max(1, $diffInMonths + 1));

            $currentStart = $calculationStartDate->copy()->startOfMonth();
            $currentEnd = $calculationStartDate->copy()->endOfMonth();

            for ($i = 0; $i < $monthsToShow; $i++) {
                // Ensure we don't go beyond the end date
                if ($currentStart->gt($calculationEndDate)) {
                    break;
                }

                $periodEnd = $currentEnd->gt($calculationEndDate) ? $calculationEndDate : $currentEnd;

                // Build invoice query with optional active filter
                $invoiceQuery = Invoice::whereBetween(
                    'invoice_date',
                    [$currentStart->format('Y-m-d'), $periodEnd->format('Y-m-d')]
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
                        [$currentStart->format('Y-m-d'), $periodEnd->format('Y-m-d')]
                    )
                        ->whereCompany()
                        ->sum('base_amount')
                );
                array_push(
                    $receipt_totals,
                    Payment::whereBetween(
                        'payment_date',
                        [$currentStart->format('Y-m-d'), $periodEnd->format('Y-m-d')]
                    )
                        ->whereCompany()
                        ->sum('base_amount')
                );
                array_push(
                    $net_income_totals,
                    ($receipt_totals[$i] - $expense_totals[$i])
                );

                array_push($months, $currentStart->translatedFormat('M'));
                $currentStart->addMonth()->startOfMonth();
                $currentEnd->addMonth()->endOfMonth();
            }
        } else {
            // Use original fiscal year logic
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

            $calculationStartDate = $startDate;
            $calculationEndDate = $start;

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
        }

        // Build total sales query with optional active filter and unified date filter
        $totalSalesQuery = Invoice::whereBetween(
            'invoice_date',
            [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]
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
            [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        $total_expenses = Expense::whereBetween(
            'expense_date',
            [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]
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

        // Build customer count query with optional active filter and unified date filter
        $customerQuery = Customer::whereCompany();
        if ($hasUnifiedFilter) {
            // For unified date filter, only count customers who have invoices/estimates in the date range
            $customerQuery->where(function ($query) use ($calculationStartDate, $calculationEndDate) {
                $query->whereHas('invoices', function ($subQuery) use ($calculationStartDate, $calculationEndDate) {
                    $subQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
                })->orWhereHas('estimates', function ($subQuery) use ($calculationStartDate, $calculationEndDate) {
                    $subQuery->whereBetween('estimate_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
                });
            });
        }
        if ($activeOnly) {
            $customerQuery->where('enable_portal', true)
                ->where(function ($query) use ($hasUnifiedFilter, $calculationStartDate, $calculationEndDate) {
                    $query->whereHas('invoices', function ($subQuery) use ($hasUnifiedFilter, $calculationStartDate, $calculationEndDate) {
                        $subQuery->whereIn('status', [
                            Invoice::STATUS_SENT,
                            Invoice::STATUS_VIEWED,
                        ])->whereIn('paid_status', [
                            Invoice::STATUS_UNPAID,
                            Invoice::STATUS_PARTIALLY_PAID,
                        ]);
                        if ($hasUnifiedFilter) {
                            $subQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
                        }
                    })->orWhereHas('estimates', function ($subQuery) use ($hasUnifiedFilter, $calculationStartDate, $calculationEndDate) {
                        $subQuery->whereIn('status', [
                            Estimate::STATUS_SENT,
                            Estimate::STATUS_VIEWED,
                        ]);
                        if ($hasUnifiedFilter) {
                            $subQuery->whereBetween('estimate_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
                        }
                    });
                });
        }
        $total_customer_count = $customerQuery->count();

        // Build invoice count query with optional active filter and unified date filter
        $invoiceCountQuery = Invoice::whereCompany();
        if ($hasUnifiedFilter) {
            $invoiceCountQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }
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

        // Build estimate count query with optional active filter and unified date filter
        $estimateCountQuery = Estimate::whereCompany();
        if ($hasUnifiedFilter) {
            $estimateCountQuery->whereBetween('estimate_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }
        if ($activeOnly) {
            $estimateCountQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }
        $total_estimate_count = $estimateCountQuery->count();

        // Build amount due query with optional active filter and unified date filter
        $amountDueQuery = Invoice::whereCompany();
        if ($hasUnifiedFilter) {
            $amountDueQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }
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

        // Build recent due invoices query with optional active filter and unified date filter
        $recentDueInvoicesQuery = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0);

        if ($hasUnifiedFilter) {
            $recentDueInvoicesQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }

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

        // Build recent estimates query with optional active filter and unified date filter
        $recentEstimatesQuery = Estimate::with('customer')->whereCompany();
        if ($hasUnifiedFilter) {
            $recentEstimatesQuery->whereBetween('estimate_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }
        if ($activeOnly) {
            $recentEstimatesQuery->whereIn('status', [
                Estimate::STATUS_SENT,
                Estimate::STATUS_VIEWED,
            ]);
        }
        $recent_estimates = $recentEstimatesQuery->take(5)->latest()->get();

        // Status Distribution with unified date filter
        $paidQuery = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_PAID);
        $overdueQuery = Invoice::whereCompany()->where('paid_status', Invoice::STATUS_UNPAID)
            ->where('due_date', '<', Carbon::now());
        $pendingQuery = Invoice::whereCompany()->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
            ->where('due_date', '>=', Carbon::now());

        // Apply unified date filter to status distribution if specified
        if ($hasUnifiedFilter) {
            $paidQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
            $overdueQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
            $pendingQuery->whereBetween('invoice_date', [$calculationStartDate->format('Y-m-d'), $calculationEndDate->format('Y-m-d')]);
        }

        // Apply active filter to status distribution if enabled
        if ($activeOnly) {
            $paidQuery->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED]);
            $overdueQuery->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED]);
            $pendingQuery->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED]);
        }

        $paid_count = $paidQuery->count();
        $overdue_count = $overdueQuery->count();
        $pending_count = $pendingQuery->count();

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
            'unified_date_filter_applied' => $request->has('start_date') || $request->has('end_date') || $request->has('date_range'),
        ]);
    }

    /**
     * Get date range based on unified filter parameters
     *
     * @return array
     */
    private function getDateRange(Request $request)
    {
        // If custom dates are provided, use them
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                'start' => Carbon::createFromFormat('Y-m-d', $request->start_date),
                'end' => Carbon::createFromFormat('Y-m-d', $request->end_date),
            ];
        }

        // Handle predefined date ranges
        $now = Carbon::now();
        $dateRange = $request->input('date_range', 'last_30_days');

        switch ($dateRange) {
            case 'all_time':
                // Return null values to indicate no date filtering
                return [
                    'start' => null,
                    'end' => null,
                ];
            case 'last_7_days':
                return [
                    'start' => $now->copy()->subDays(7),
                    'end' => $now->copy(),
                ];
            case 'last_30_days':
                return [
                    'start' => $now->copy()->subDays(30),
                    'end' => $now->copy(),
                ];
            case 'last_90_days':
                return [
                    'start' => $now->copy()->subDays(90),
                    'end' => $now->copy(),
                ];
            case 'last_12_months':
                return [
                    'start' => $now->copy()->subMonths(12),
                    'end' => $now->copy(),
                ];
            default:
                // Default to last 30 days
                return [
                    'start' => $now->copy()->subDays(30),
                    'end' => $now->copy(),
                ];
        }
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
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $company = Company::find($request->header('company'));
        $this->authorize('view dashboard', $company);

        $type = $request->input('type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $baseQuery = Invoice::whereCompany()
            ->whereIn('paid_status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID]);

        // Apply date filter only if dates are provided
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('invoice_date', [$startDate, $endDate]);
        }

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

        // Convert cents to dollars
        $results = $results->map(function ($item) {
            $item->value = $item->value / 100;

            return $item;
        });

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
            'period_months' => 'required|integer|in:6,9,12,15',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $company = Company::find($request->header('company'));
        $this->authorize('view dashboard', $company);

        $periodMonths = $request->input('period_months');
        $customerId = $request->input('customer_id');
        $requestStartDate = $request->input('start_date');
        $requestEndDate = $request->input('end_date');

        $today = Carbon::today();

        // If specific dates are provided (not "All time"), use them as a base
        if ($requestStartDate && $requestEndDate) {
            $baseStartDate = Carbon::createFromFormat('Y-m-d', $requestStartDate);
            $baseEndDate = Carbon::createFromFormat('Y-m-d', $requestEndDate);

            // Extend the range to include some past and future months for prediction
            $pastMonths = 3;
            $futureMonths = $periodMonths - $pastMonths;

            $startDate = $baseStartDate->copy()->subMonths($pastMonths)->startOfMonth();
            $endDate = $baseEndDate->copy()->addMonths($futureMonths)->endOfMonth();
        } else {
            // For "All time", use a reasonable default range
            $pastMonths = 6;
            $futureMonths = $periodMonths - $pastMonths;

            $startDate = $today->copy()->subMonths($pastMonths)->startOfMonth();
            $endDate = $today->copy()->addMonths($futureMonths)->endOfMonth();
        }

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
