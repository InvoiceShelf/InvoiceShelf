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
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
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

            // Include filter state in response for debugging
            'active_filter_applied' => $activeOnly,
        ]);
    }
}
