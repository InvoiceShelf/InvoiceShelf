<?php

namespace App\Domains\Reporting\Http\Controllers\Company;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Reporting\Queries\ReceivablesAgingQuery;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

/**
 * The company overview: a twelve-month money series, the headline counters and
 * the two "latest activity" lists.
 *
 * The series is anchored on the company's `fiscal_year` preference, whose first
 * dash-separated component names the opening month. Anything the parser cannot
 * read — the shipped default is the word "calendar_year" — intval()s to zero,
 * and month zero rolls Carbon back into December of the year before. That is
 * the window those companies really get, so it is reproduced rather than
 * corrected.
 */
class DashboardController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function __invoke(Request $request, ReceivablesAgingQuery $receivables)
    {
        $companyId = $request->header('company');

        $this->authorize('view dashboard', Company::find($companyId));

        $openingMonth = intval(explode('-', CompanySetting::getSetting('fiscal_year', $companyId))[0]);

        // Three cursors over the same starting instant: the fixed left edge of
        // the whole window, and the pair that walks it a month at a time.
        $windowStart = Carbon::now();
        $monthStart = Carbon::now();
        $monthEnd = Carbon::now();

        // A fiscal year whose opening month is still ahead in the calendar year
        // is the one that opened twelve months ago.
        $openedLastYear = $openingMonth > $monthStart->month;

        foreach ([$windowStart, $monthStart, $monthEnd] as $cursor) {
            if ($openedLastYear) {
                $cursor->subYear();
            }

            $cursor->month($openingMonth);
        }

        $windowStart->startOfMonth();
        $monthStart->startOfMonth();
        $monthEnd->endOfMonth();

        // The key's presence is the whole signal — its value is never read.
        $previousYear = $request->has('previous_year');

        if ($previousYear) {
            $windowStart->subYear()->startOfMonth();
            $monthStart->subYear()->startOfMonth();
            $monthEnd->subYear()->endOfMonth();
        }

        $months = [];
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netIncomeTotals = [];

        for ($bucket = 0; $bucket < 12; $bucket++) {
            $bucketSpan = [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];

            $invoiceTotals[] = Invoice::query()
                ->whereBetween('invoice_date', $bucketSpan)
                ->whereCompany()
                ->sum('base_total');

            $expenseTotals[] = Expense::query()
                ->whereBetween('expense_date', $bucketSpan)
                ->whereCompany()
                ->sum('base_amount');

            $receiptTotals[] = Payment::query()
                ->whereBetween('payment_date', $bucketSpan)
                ->whereCompany()
                ->sum('base_amount');

            // Net income is what came in less what went out. Invoiced money is
            // not part of it — only money actually received counts.
            $netIncomeTotals[] = $receiptTotals[$bucket] - $expenseTotals[$bucket];

            $months[] = $monthStart->translatedFormat('M');

            // Both cursors step forward off the first of their month, so a
            // short month can never drag the walk backwards.
            $monthEnd->startOfMonth()->addMonth()->endOfMonth();
            $monthStart->addMonth()->startOfMonth();
        }

        // Twelve steps left the walking cursor on the month after the window.
        // Back it up on to the last month and take that month's final day as
        // the right edge of the whole-window figures.
        $monthStart->subMonth()->endOfMonth();

        $windowSpan = [$windowStart->format('Y-m-d'), $monthStart->format('Y-m-d')];

        $totalSales = Invoice::query()
            ->whereBetween('invoice_date', $windowSpan)
            ->whereCompany()
            ->sum('base_total');

        $totalReceipts = Payment::query()
            ->whereBetween('payment_date', $windowSpan)
            ->whereCompany()
            ->sum('base_amount');

        $totalExpenses = Expense::query()
            ->whereBetween('expense_date', $windowSpan)
            ->whereCompany()
            ->sum('base_amount');

        $totalNetIncome = (int) $totalReceipts - (int) $totalExpenses;

        $chartData = [
            'months' => $months,
            'invoice_totals' => $invoiceTotals,
            'expense_totals' => $expenseTotals,
            'receipt_totals' => $receiptTotals,
            'net_income_totals' => $netIncomeTotals,
        ];

        $customerCount = Customer::query()->whereCompany()->count();

        // "How many invoices did we issue" counts issued documents, so the
        // reversals are left out. The money figures above deliberately keep
        // them: a credit note's negated total is exactly what nets a sale back
        // out. The outstanding sum below keeps them too, which is a quirk
        // rather than a decision — a credit note's due amount is always zero,
        // so it adds nothing, and the sum has always been taken over the lot.
        $invoiceCount = Invoice::query()
            ->whereCompany()
            ->where('type', Invoice::TYPE_INVOICE)
            ->count();

        $estimateCount = Estimate::query()->whereCompany()->count();

        $amountDue = Invoice::query()
            ->whereCompany()
            ->sum('base_due_amount');

        // Raw models rather than InvoiceResource: each loaded relation is
        // serialized with the full $appends set, so a column-limited
        // creditNotes load blew up inside the date accessors (the children
        // arrive without company_id) and loading them whole would run those
        // appends per credit note for nothing. Neither list needs the relation
        // anyway — credited_status is a resource-level field, and a fully
        // credited invoice has no due amount left, so it never reaches here.
        $recentDueInvoices = Invoice::with('customer')
            ->whereCompany()->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();

        $recentEstimates = Estimate::with('customer')
            ->whereCompany()
            ->take(5)
            ->latest()
            ->get();

        // Both lists are gated on the viewer's own document rights and come
        // back empty — never absent — when those are missing. The counters and
        // the money figures are not gated at all: holding the dashboard
        // ability is enough to see company revenue.
        return response()->json([
            'total_amount_due' => $amountDue,
            'receivables' => $receivables->summary($companyId, Carbon::now()),
            'total_customer_count' => $customerCount,
            'total_invoice_count' => $invoiceCount,
            'total_estimate_count' => $estimateCount,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recentDueInvoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recentEstimates : [],

            'chart_data' => $chartData,

            'total_sales' => $totalSales,
            'total_receipts' => $totalReceipts,
            'total_expenses' => $totalExpenses,
            'total_net_income' => $totalNetIncome,
        ]);
    }
}
