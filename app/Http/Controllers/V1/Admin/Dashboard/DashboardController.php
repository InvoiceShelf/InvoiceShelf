<?php

namespace InvoiceShelf\Http\Controllers\V1\Admin\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;
use InvoiceShelf\Http\Controllers\Controller;
use InvoiceShelf\Models\Company;
use InvoiceShelf\Models\CompanySetting;
use InvoiceShelf\Models\Customer;
use InvoiceShelf\Models\Estimate;
use InvoiceShelf\Models\Expense;
use InvoiceShelf\Models\Invoice;
use InvoiceShelf\Models\Payment;
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

        if ($terms[0] <= $start->month) {
            $startDate->month($terms[0])->startOfMonth();
            $start->month($terms[0])->startOfMonth();
            $end->month($terms[0])->endOfMonth();
        } else {
            $startDate->subYear()->month($terms[0])->startOfMonth();
            $start->subYear()->month($terms[0])->startOfMonth();
            $end->subYear()->month($terms[0])->endOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        while ($monthCounter < 12) {
            array_push(
                $invoice_totals,
                Invoice::whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->sum('base_total')
            );
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
            array_push($months, $start->format('M'));
            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        $total_sales = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_total');

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

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        $total_amount_due = Invoice::whereCompany()
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

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
        ]);
    }
}
